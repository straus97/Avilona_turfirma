<?php

namespace Tests\Unit;

use App\Services\Legal\LegalArchiveException;
use App\Services\Legal\LegalRegistry;
use PHPUnit\Framework\TestCase;

/**
 * NOV-PUBLIC-LEGAL-01 S1-A: strict legal registry validator (App\Services\Legal\LegalRegistry).
 * Pure PHPUnit, no application boot. All registry data is synthetic: hashes are derived from
 * placeholder strings and approval references are invented; no real legal document, hash or decision.
 */
class LegalRegistryTest extends TestCase
{
    private const FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    private const ASSETS = [
        'personal-data-policy' => 'documents/Policy_regarding_the_protection_and_processing_of_personal_data.pdf',
        'separate-services-terms' => 'documents/Public_offer_for_ticket_sales_and_hotel_bookings.pdf',
        'tour-product-terms' => 'documents/Public_offer_to_conclude_an_agreement_on_the_sale_of_a_tourism_product.pdf',
        'travel-memo-general' => 'documents/Reminder_for_those_traveling_abroad_general.pdf',
        'user-agreement' => 'documents/User_Agreement.pdf',
    ];

    // ------------------------------------------------------------------------------------- valid input

    public function test_valid_canonical_registry_is_accepted(): void
    {
        $registry = LegalRegistry::parse(self::encode(self::data()));

        $this->assertSame([
            'inquiry-consent-text', 'personal-data-policy', 'separate-services-terms',
            'tour-product-terms', 'travel-memo-general', 'user-agreement',
        ], $registry->documentKeys());
        $this->assertSame(LegalRegistry::DOCUMENT_KEYS, $registry->documentKeys());
        $this->assertSame('rev-0001', $registry->currentVersion('user-agreement')['version_id']);
        $this->assertSame(['type' => 'page', 'route_name' => 'personal_data_consent.info'], $registry->surface('inquiry-consent-text'));
        $this->assertSame(['POLICY'], $registry->placeholders('inquiry-consent-text', 'rev-0001'));
        $this->assertFalse($registry->isRestricted('user-agreement', 'rev-0001'));
        $this->assertSame(hash('sha256', self::encode(self::data())), $registry->sha256());

        $artifacts = $registry->artifacts();
        $this->assertCount(6, $artifacts);
        $this->assertSame('versions/inquiry-consent-text/rev-0001/fragment.html', $artifacts[0]['path']);
        $this->assertSame('resources/legal-archive/versions/inquiry-consent-text/rev-0001/fragment.html', $artifacts[0]['repository_path']);
        $this->assertSame('versions/user-agreement/rev-0001/document.pdf', $artifacts[5]['path']);
        $this->assertSame(LegalRegistry::CONTENT_PDF, $artifacts[5]['content_type']);
    }

    public function test_public_file_surface_keeps_repository_path_and_asset_path_separate(): void
    {
        $registry = LegalRegistry::parse(self::encode(self::data()));
        $files = $registry->publicFiles();

        $this->assertSame(array_keys(self::ASSETS), array_keys($files));
        foreach (self::ASSETS as $key => $asset) {
            $this->assertSame(['asset_path' => $asset, 'repository_path' => 'public/' . $asset], $files[$key]);
            $this->assertStringStartsNotWith('public/', $files[$key]['asset_path'], 'the URL input never contains public/');
            $this->assertStringNotContainsString('://', $files[$key]['asset_path'], 'no URL is produced by the pure registry');
        }
        $this->assertSame(
            ['POLICY' => ['document_key' => 'personal-data-policy', 'version_id' => 'rev-0001', 'asset_path' => self::ASSETS['personal-data-policy']]],
            $registry->linkTargets('inquiry-consent-text', 'rev-0001')
        );
        $this->assertSame(self::ASSETS, LegalRegistry::PUBLIC_FILE_BINDINGS);
    }

    public function test_forward_reactivation_of_an_earlier_unrestricted_version_is_accepted(): void
    {
        $data = self::reactivateFirstVersions(self::withSecondPolicyVersion(self::data()));

        $registry = LegalRegistry::parse(self::encode($data));

        $this->assertSame('rev-0001', $registry->currentVersion('personal-data-policy')['version_id']);
        $this->assertSame('rev-0001', $registry->currentVersion('inquiry-consent-text')['version_id']);
        $this->assertSame(self::data()['documents'][0]['versions'][0], $registry->versions('inquiry-consent-text')[0],
            'history is appended, never rewritten, in these fixtures');
        $this->assertCount(3, $registry->activations('personal-data-policy'));
        $this->assertCount(2, $registry->versions('personal-data-policy'));
    }

    // ------------------------------------------------------------------------------------- bytes

    public function test_byte_encoding_is_strict(): void
    {
        $valid = self::encode(self::data());

        $this->assertRegistryRejected('registry_encoding', "\xEF\xBB\xBF" . $valid, 'BOM');
        $this->assertRegistryRejected('registry_encoding', str_replace("\n", "\r\n", $valid), 'CRLF');
        $this->assertRegistryRejected('registry_encoding', substr($valid, 0, -1), 'missing final LF');
        $this->assertRegistryRejected('registry_encoding', $valid . "\n", 'two final LF');
        $this->assertRegistryRejected('registry_encoding', str_replace('SYN-APR-POLICY-1', "SYN-APR-POLICY-\u{00E9}", $valid), 'non-ASCII');
        $this->assertRegistryRejected('registry_encoding', str_replace('    "format"', "\t\"format\"", $valid), 'tab');
        $this->assertRegistryRejected('registry_size', '', 'empty');
        $this->assertRegistryRejected('registry_size', str_repeat(' ', LegalRegistry::MAX_BYTES) . "\n", 'oversized');
    }

    public function test_malformed_and_noncanonical_json_is_rejected(): void
    {
        $valid = self::encode(self::data());

        $this->assertRegistryRejected('registry_json', "{\n    \"format\": 1,\n", 'truncated');
        $this->assertRegistryRejected('registry_json', "not json\n", 'not JSON');
        $this->assertRegistryRejected('registry_noncanonical', json_encode(self::data(), JSON_UNESCAPED_SLASHES) . "\n", 'compact');
        $this->assertRegistryRejected('registry_noncanonical', str_replace('    ', '  ', $valid), 'two-space indentation');
        $this->assertRegistryRejected('registry_noncanonical', str_replace('": ', '":', $valid), 'separator spacing');
        $this->assertRegistryRejected('registry_noncanonical', str_replace('documents/', 'documents\\/', $valid), 'escaped slash');
        $this->assertRegistryRejected('registry_noncanonical', str_replace('"restrictions": []', '"restrictions": {}', $valid), 'object for list');
    }

    public function test_duplicate_member_names_are_rejected_including_escaped_spellings(): void
    {
        $valid = self::encode(self::data());

        $this->assertRegistryRejected('registry_noncanonical', str_replace("    \"format\": 1,\n", "    \"format\": 1,\n    \"format\": 1,\n", $valid), 'plain duplicate');
        $this->assertRegistryRejected('registry_noncanonical', str_replace("    \"format\": 1,\n", "    \"form\\u0061t\": 1,\n    \"format\": 1,\n", $valid), 'escaped duplicate');
        $this->assertRegistryRejected('registry_noncanonical', str_replace('"format": 1', '"form\\u0061t": 1', $valid), 'escaped spelling');
    }

    // ------------------------------------------------------------------------------------- structure

    public function test_fields_must_be_exact_and_in_order(): void
    {
        $data = self::data();
        $reordered = ['hash_algorithm' => $data['hash_algorithm'], 'format' => $data['format'], 'documents' => $data['documents']];
        $this->assertRegistryRejected('registry_fields', self::encode($reordered), 'top-level order');

        $extra = self::data();
        $extra['documents'][2]['versions'][0]['record_digest'] = str_repeat('0', 64);
        $this->assertRegistryRejected('registry_fields', self::encode($extra), 'record_digest is not part of the contract');

        $missing = self::data();
        unset($missing['documents'][3]['restrictions']);
        $this->assertRegistryRejected('registry_fields', self::encode($missing), 'missing field');

        $versionOrder = self::data();
        $v = $versionOrder['documents'][4]['versions'][0];
        $versionOrder['documents'][4]['versions'][0] = ['locked' => $v['locked'], 'version_id' => $v['version_id']] + $v;
        $this->assertRegistryRejected('registry_fields', self::encode($versionOrder), 'version field order');

        $linkExtra = self::data();
        $linkExtra['documents'][0]['versions'][0]['links'][0]['url'] = '/documents/x.pdf';
        $this->assertRegistryRejected('registry_fields', self::encode($linkExtra), 'authored URL in a link');

        $legacy = self::data();
        $legacy['documents'][5]['versions'][0]['lifecycle_status'] = 'legacy';
        $this->assertRegistryRejected('registry_fields', self::encode($legacy), 'legacy lifecycle field');
    }

    public function test_types_are_strict_and_never_coerced(): void
    {
        $cases = [
            'format as string' => ['registry_format', fn (array &$d) => $d['format'] = '1'],
            'format 2' => ['registry_format', fn (array &$d) => $d['format'] = 2],
            'hash algorithm' => ['registry_format', fn (array &$d) => $d['hash_algorithm'] = 'sha1'],
            'documents as object' => ['registry_type', fn (array &$d) => $d['documents'] = ['a' => 1]],
            'bytes as numeric string' => ['registry_bytes', fn (array &$d) => $d['documents'][5]['versions'][0]['bytes'] = '1200'],
            'bytes as float' => ['registry_bytes', fn (array &$d) => $d['documents'][5]['versions'][0]['bytes'] = 1200.5],
            'locked as integer' => ['registry_version_locked', fn (array &$d) => $d['documents'][5]['versions'][0]['locked'] = 1],
            'locked as string' => ['registry_version_locked', fn (array &$d) => $d['documents'][5]['versions'][0]['locked'] = 'true'],
            'links as object' => ['registry_type', fn (array &$d) => $d['documents'][0]['versions'][0]['links'] = ['POLICY' => 'x']],
            'versions as string' => ['registry_type', fn (array &$d) => $d['documents'][1]['versions'] = 'rev-0001'],
        ];
        foreach ($cases as $label => [$reason, $mutate]) {
            $data = self::data();
            $mutate($data);
            $this->assertRegistryRejected($reason, self::encode($data), $label);
        }
    }

    public function test_exactly_the_six_documents_in_key_order_are_required(): void
    {
        $missing = self::data();
        array_pop($missing['documents']);
        $this->assertRegistryRejected('registry_documents', self::encode($missing), 'missing');

        $extra = self::data();
        $extra['documents'][] = $extra['documents'][5];
        $this->assertRegistryRejected('registry_documents', self::encode($extra), 'duplicate document key');

        $swapped = self::data();
        [$swapped['documents'][1], $swapped['documents'][2]] = [$swapped['documents'][2], $swapped['documents'][1]];
        $this->assertRegistryRejected('registry_documents', self::encode($swapped), 'order');

        $renamed = self::data();
        $renamed['documents'][5]['key'] = 'User-Agreement';
        $this->assertRegistryRejected('registry_documents', self::encode($renamed), 'case variant key');
    }

    // ------------------------------------------------------------------------------------- versions

    public function test_version_ids_are_sequential_rev_ids(): void
    {
        foreach (['rev-0002', 'leg-0001', 'rev-1', 'REV-0001', 'rev-00001', 'rev-0001 '] as $id) {
            $data = self::data();
            $data['documents'][5]['versions'][0]['version_id'] = $id;
            $this->assertRegistryRejected('registry_version_id', self::encode($data), $id);
        }

        $duplicate = self::withSecondPolicyVersion(self::data());
        $duplicate['documents'][1]['versions'][1]['version_id'] = 'rev-0001';
        $this->assertRegistryRejected('registry_version_id', self::encode($duplicate), 'duplicate id');

        $empty = self::data();
        $empty['documents'][5]['versions'] = [];
        $this->assertRegistryRejected('registry_version_id', self::encode($empty), 'no versions');
    }

    public function test_version_records_must_be_locked(): void
    {
        $data = self::data();
        $data['documents'][2]['versions'][0]['locked'] = false;

        $this->assertRegistryRejected('registry_version_locked', self::encode($data));
    }

    public function test_approval_reference_must_be_structurally_valid_and_unique_per_document(): void
    {
        foreach (['', ' SYN', 'SYN APR', '-SYN', 'SYN-', str_repeat('A', 129), "SYN\u{0001}", null, 7] as $reference) {
            $data = self::data();
            $data['documents'][3]['versions'][0]['approval_reference'] = $reference;
            $this->assertRegistryRejected('registry_approval_reference', self::encode($data), var_export($reference, true));
        }

        $duplicate = self::withSecondPolicyVersion(self::data());
        $duplicate['documents'][1]['versions'][1]['approval_reference'] = 'SYN-APR-POLICY-1';
        $this->assertRegistryRejected('registry_approval_reference', self::encode($duplicate), 'duplicate in one document');

        $shared = self::data();
        foreach (array_keys($shared['documents']) as $i) {
            $shared['documents'][$i]['versions'][0]['approval_reference'] = 'SYN-BOARD-2027-01';
        }
        $this->assertSame(6, count(LegalRegistry::parse(self::encode($shared))->artifacts()), 'one decision may approve several documents');
    }

    public function test_content_type_and_derived_path_are_enforced(): void
    {
        $type = self::data();
        $type['documents'][5]['versions'][0]['content_type'] = 'text/html';
        $this->assertRegistryRejected('registry_content_type', self::encode($type), 'PDF document as HTML');

        $pageType = self::data();
        $pageType['documents'][0]['versions'][0]['content_type'] = 'application/pdf';
        $this->assertRegistryRejected('registry_content_type', self::encode($pageType), 'page as PDF');

        foreach ([
            'versions/user-agreement/rev-0001/fragment.html',
            'versions/User-Agreement/rev-0001/document.pdf',
            'versions/user-agreement/rev-0001/Document.pdf',
            'versions/user-agreement/../user-agreement/rev-0001/document.pdf',
            'resources/legal-archive/versions/user-agreement/rev-0001/document.pdf',
            '/versions/user-agreement/rev-0001/document.pdf',
            'versions\\user-agreement\\rev-0001\\document.pdf',
            'versions/user-agreement/rev-0001/document.pdf/',
        ] as $path) {
            $data = self::data();
            $data['documents'][5]['versions'][0]['path'] = $path;
            $this->assertRegistryRejected('registry_path', self::encode($data), $path);
        }
    }

    public function test_sizes_and_hashes_are_strict(): void
    {
        foreach ([0, -1, LegalRegistry::MAX_PDF_BYTES + 1] as $bytes) {
            $data = self::data();
            $data['documents'][5]['versions'][0]['bytes'] = $bytes;
            $this->assertRegistryRejected('registry_bytes', self::encode($data), (string) $bytes);
        }
        $html = self::data();
        $html['documents'][0]['versions'][0]['bytes'] = LegalRegistry::MAX_FRAGMENT_BYTES + 1;
        $this->assertRegistryRejected('registry_bytes', self::encode($html), 'fragment above 64 KiB');

        foreach ([strtoupper(hash('sha256', 'x')), substr(hash('sha256', 'x'), 1), hash('sha256', 'x') . '0', hash('md5', 'x'), ''] as $sha) {
            $data = self::data();
            $data['documents'][5]['versions'][0]['sha256'] = $sha;
            $this->assertRegistryRejected('registry_hash', self::encode($data), $sha);
        }
    }

    // ------------------------------------------------------------------------------------- history

    public function test_activation_dates_are_real_calendar_dates_in_order(): void
    {
        foreach (['2027-02-29', '2026-02-30', '2026-13-01', '2026-00-10', '0000-01-01', '26-01-01', '2026-1-01', '2026-01-01T00:00', '', null] as $date) {
            $data = self::data();
            $data['documents'][4]['activations'][0]['effective_from'] = $date;
            $this->assertRegistryRejected('registry_date', self::encode($data), var_export($date, true));
        }

        $leap = self::data();
        $leap['documents'][4]['activations'][0]['effective_from'] = '2028-02-29';
        $this->assertSame('rev-0001', LegalRegistry::parse(self::encode($leap))->currentVersion('travel-memo-general')['version_id']);

        $backwards = self::withSecondPolicyVersion(self::data());
        $backwards['documents'][1]['activations'][1]['effective_from'] = '2026-12-31';
        $this->assertRegistryRejected('registry_date', self::encode($backwards), 'activation earlier than the previous one');
    }

    public function test_activations_must_resolve_and_follow_the_version_sequence(): void
    {
        $none = self::data();
        $none['documents'][5]['activations'] = [];
        $this->assertRegistryRejected('registry_activation', self::encode($none), 'no current version');

        $unknown = self::data();
        $unknown['documents'][5]['activations'][0]['version_id'] = 'rev-0002';
        $this->assertRegistryRejected('registry_activation', self::encode($unknown), 'unknown version');

        $repeat = self::data();
        $repeat['documents'][5]['activations'][] = self::activation('rev-0001', 'SYN-ACT-UA-2', '2027-02-01');
        $this->assertRegistryRejected('registry_activation', self::encode($repeat), 'current version activated again');

        $dormant = self::withNewPolicyOnly(self::data());
        array_pop($dormant['documents'][1]['activations']);
        $this->assertRegistryRejected('registry_activation', self::encode($dormant), 'version never activated (no drafts)');

        $outOfOrder = self::withNewPolicyOnly(self::data());
        $outOfOrder['documents'][1]['activations'][0]['version_id'] = 'rev-0002';
        $outOfOrder['documents'][1]['activations'][1]['version_id'] = 'rev-0001';
        $this->assertRegistryRejected('registry_activation', self::encode($outOfOrder), 'rev-0002 activated before rev-0001');
    }

    public function test_references_are_structural_and_never_conflict(): void
    {
        $grammar = self::data();
        $grammar['documents'][5]['activations'][0]['reference'] = 'SYN ACT';
        $this->assertRegistryRejected('registry_reference', self::encode($grammar), 'activation reference grammar');

        $duplicate = self::withSecondPolicyVersion(self::data());
        $duplicate['documents'][1]['activations'][1]['reference'] = 'SYN-ACT-POLICY-1';
        $this->assertRegistryRejected('registry_reference', self::encode($duplicate), 'repeated activation reference');

        $conflict = self::withSecondPolicyVersion(self::data());
        $conflict['documents'][1]['restrictions'][] = ['version_id' => 'rev-0001', 'reference' => 'SYN-ACT-POLICY-2'];
        $this->assertRegistryRejected('registry_reference', self::encode($conflict), 'restriction reuses an activation reference');
    }

    public function test_restricted_versions_cannot_be_current(): void
    {
        $restricted = self::data();
        $restricted['documents'][5]['restrictions'][] = ['version_id' => 'rev-0001', 'reference' => 'SYN-RES-UA-1'];
        $this->assertRegistryRejected('registry_current_restricted', self::encode($restricted), 'restricted current');

        $reactivated = self::reactivateFirstVersions(self::withSecondPolicyVersion(self::data()));
        $reactivated['documents'][1]['restrictions'][] = ['version_id' => 'rev-0001', 'reference' => 'SYN-RES-POLICY-1'];
        $this->assertRegistryRejected('registry_current_restricted', self::encode($reactivated), 'reactivation of a restricted version');

        $historical = self::withSecondPolicyVersion(self::data());
        $historical['documents'][1]['restrictions'][] = ['version_id' => 'rev-0001', 'reference' => 'SYN-RES-POLICY-1'];
        $registry = LegalRegistry::parse(self::encode($historical));
        $this->assertTrue($registry->isRestricted('personal-data-policy', 'rev-0001'), 'a superseded version may be restricted');

        $unknown = self::data();
        $unknown['documents'][5]['restrictions'][] = ['version_id' => 'rev-0009', 'reference' => 'SYN-RES-UA-1'];
        $this->assertRegistryRejected('registry_restriction', self::encode($unknown), 'unknown version');

        $twice = self::withSecondPolicyVersion(self::data());
        $twice['documents'][1]['restrictions'][] = ['version_id' => 'rev-0001', 'reference' => 'SYN-RES-POLICY-1'];
        $twice['documents'][1]['restrictions'][] = ['version_id' => 'rev-0001', 'reference' => 'SYN-RES-POLICY-2'];
        $this->assertRegistryRejected('registry_restriction', self::encode($twice), 'restricted twice');
    }

    // ------------------------------------------------------------------------------------- links and surfaces

    public function test_links_resolve_exact_versions_and_the_current_consent_links_current_targets(): void
    {
        $this->assertRegistryRejected('registry_current_link', self::encode(self::withNewPolicyOnly(self::data())),
            'current consent links a superseded policy');

        $cases = [
            'unresolved version' => fn (array &$d) => $d['documents'][0]['versions'][0]['links'][0]['version_id'] = 'rev-0002',
            'unknown document' => fn (array &$d) => $d['documents'][0]['versions'][0]['links'][0]['document_key'] = 'privacy',
            'page as target' => fn (array &$d) => $d['documents'][0]['versions'][0]['links'][0]['document_key'] = 'inquiry-consent-text',
            'lowercase placeholder' => fn (array &$d) => $d['documents'][0]['versions'][0]['links'][0]['placeholder'] = 'policy',
            'placeholder syntax' => fn (array &$d) => $d['documents'][0]['versions'][0]['links'][0]['placeholder'] = '%%LEGAL_LINK:POLICY%%',
            'duplicate placeholder' => fn (array &$d) => $d['documents'][0]['versions'][0]['links'][] = $d['documents'][0]['versions'][0]['links'][0],
            'PDF with a link' => fn (array &$d) => $d['documents'][5]['versions'][0]['links'][] = $d['documents'][0]['versions'][0]['links'][0],
        ];
        foreach ($cases as $label => $mutate) {
            $data = self::data();
            $mutate($data);
            $this->assertRegistryRejected('registry_links', self::encode($data), $label);
        }

        $twoLinks = self::data();
        $twoLinks['documents'][0]['versions'][0]['links'][] = ['placeholder' => 'TERMS', 'document_key' => 'user-agreement', 'version_id' => 'rev-0001'];
        $this->assertSame(['POLICY', 'TERMS'], LegalRegistry::parse(self::encode($twoLinks))->placeholders('inquiry-consent-text', 'rev-0001'));

        $history = LegalRegistry::parse(self::encode(self::withSecondPolicyVersion(self::data())));
        $this->assertSame('rev-0002', $history->currentVersion('inquiry-consent-text')['version_id']);
        $this->assertSame('rev-0001', $history->linkTargets('inquiry-consent-text', 'rev-0001')['POLICY']['version_id'],
            'a superseded consent version keeps its exact historical link');
    }

    public function test_every_consent_version_binds_the_policy_exactly_once_through_the_policy_placeholder(): void
    {
        $toPolicy = ['placeholder' => 'POLICY', 'document_key' => 'personal-data-policy', 'version_id' => 'rev-0001'];
        $toAgreement = ['placeholder' => 'TERMS', 'document_key' => 'user-agreement', 'version_id' => 'rev-0001'];
        $cases = [
            'no link at all' => [],
            'only another document' => [$toAgreement],
            'POLICY targets another document' => [['document_key' => 'user-agreement'] + $toPolicy],
            'policy under another name' => [['placeholder' => 'PRIVACY'] + $toPolicy],
            'policy linked a second time' => [$toPolicy, ['placeholder' => 'PRIVACY'] + $toPolicy],
        ];
        foreach ($cases as $label => $links) {
            $data = self::data();
            // array "+" keeps the left operand's key order; restore the canonical field order.
            $data['documents'][0]['versions'][0]['links'] = array_map(
                static fn (array $l): array => ['placeholder' => $l['placeholder'], 'document_key' => $l['document_key'], 'version_id' => $l['version_id']],
                $links
            );
            $this->assertRegistryRejected('registry_policy_link', self::encode($data), $label);
        }

        $historical = self::withSecondPolicyVersion(self::data());
        $historical['documents'][0]['versions'][0]['links'] = [$toAgreement];
        $this->assertRegistryRejected('registry_policy_link', self::encode($historical), 'superseded consent versions are bound too');

        $several = self::data();
        $several['documents'][0]['versions'][0]['links'] = [$toAgreement, $toPolicy];
        $this->assertSame(['TERMS', 'POLICY'], LegalRegistry::parse(self::encode($several))->placeholders('inquiry-consent-text', 'rev-0001'));
    }

    public function test_page_versions_are_limited_to_what_the_package_checker_retains(): void
    {
        $build = static function (int $count): array {
            $data = self::data();
            for ($n = 2; $n <= $count; $n++) {
                $id = sprintf('rev-%04d', $n);
                $data['documents'][0]['versions'][] = self::version('inquiry-consent-text', $id, LegalRegistry::CONTENT_HTML, "A$n", [
                    ['placeholder' => 'POLICY', 'document_key' => 'personal-data-policy', 'version_id' => 'rev-0001'],
                ]);
                $data['documents'][0]['activations'][] = self::activation($id, "C$n", '2027-01-15');
            }

            return $data;
        };

        $this->assertCount(LegalRegistry::MAX_PAGE_VERSIONS,
            LegalRegistry::parse(self::encode($build(LegalRegistry::MAX_PAGE_VERSIONS)))->versions('inquiry-consent-text'));
        $this->assertRegistryRejected('registry_limit', self::encode($build(LegalRegistry::MAX_PAGE_VERSIONS + 1)));
    }

    public function test_surfaces_bind_the_existing_route_and_public_files_exactly(): void
    {
        $cases = [
            'asset path with public/' => fn (array &$d) => $d['documents'][5]['surface']['asset_path'] = 'public/documents/User_Agreement.pdf',
            'leading slash' => fn (array &$d) => $d['documents'][5]['surface']['asset_path'] = '/documents/User_Agreement.pdf',
            'traversal' => fn (array &$d) => $d['documents'][5]['surface']['asset_path'] = 'documents/../User_Agreement.pdf',
            'url' => fn (array &$d) => $d['documents'][5]['surface']['asset_path'] = 'https://example.invalid/documents/User_Agreement.pdf',
            'case variant' => function (array &$d): void {
                $d['documents'][5]['surface']['asset_path'] = 'documents/user_agreement.pdf';
                $d['documents'][5]['surface']['repository_path'] = 'public/documents/user_agreement.pdf';
            },
            'another existing binding' => function (array &$d): void {
                $d['documents'][5]['surface']['asset_path'] = self::ASSETS['personal-data-policy'];
                $d['documents'][5]['surface']['repository_path'] = 'public/' . self::ASSETS['personal-data-policy'];
            },
            'repository path mismatch' => fn (array &$d) => $d['documents'][5]['surface']['repository_path'] = 'storage/documents/User_Agreement.pdf',
            'repository path case' => fn (array &$d) => $d['documents'][5]['surface']['repository_path'] = 'Public/documents/User_Agreement.pdf',
            'surface type' => fn (array &$d) => $d['documents'][5]['surface']['type'] = 'page',
            'other route' => fn (array &$d) => $d['documents'][0]['surface']['route_name'] = 'review_personal_data_consent.info',
        ];
        foreach ($cases as $label => $mutate) {
            $data = self::data();
            $mutate($data);
            $this->assertRegistryRejected('registry_surface', self::encode($data), $label);
        }

        $pageAsFile = self::data();
        $pageAsFile['documents'][0]['surface'] = self::data()['documents'][5]['surface'];
        $this->assertRegistryRejected('registry_fields', self::encode($pageAsFile), 'the consent document is a page');
    }

    // ------------------------------------------------------------------------------------- fixtures

    private function assertRegistryRejected(string $reason, string $raw, string $label = ''): void
    {
        try {
            LegalRegistry::parse($raw);
        } catch (LegalArchiveException $e) {
            $this->assertSame($reason, $e->reason, "$label: " . $e->getMessage());

            return;
        }
        $this->fail("$label: registry must be rejected with $reason");
    }

    private static function encode(array $data): string
    {
        return json_encode($data, self::FLAGS) . "\n";
    }

    /** Six documents, each with one synthetic active rev-0001; the consent links the current policy. */
    private static function data(): array
    {
        $documents = [[
            'key' => 'inquiry-consent-text',
            'surface' => ['type' => 'page', 'route_name' => 'personal_data_consent.info'],
            'versions' => [self::version('inquiry-consent-text', 'rev-0001', LegalRegistry::CONTENT_HTML, 'SYN-APR-CONSENT-1', [
                ['placeholder' => 'POLICY', 'document_key' => 'personal-data-policy', 'version_id' => 'rev-0001'],
            ])],
            'activations' => [self::activation('rev-0001', 'SYN-ACT-CONSENT-1', '2027-01-15')],
            'restrictions' => [],
        ]];
        $tags = ['personal-data-policy' => 'POLICY', 'separate-services-terms' => 'SERVICES', 'tour-product-terms' => 'TOUR',
            'travel-memo-general' => 'MEMO', 'user-agreement' => 'UA'];
        foreach (self::ASSETS as $key => $asset) {
            $documents[] = [
                'key' => $key,
                'surface' => ['type' => 'public-file', 'asset_path' => $asset, 'repository_path' => 'public/' . $asset],
                'versions' => [self::version($key, 'rev-0001', LegalRegistry::CONTENT_PDF, 'SYN-APR-' . $tags[$key] . '-1', [])],
                'activations' => [self::activation('rev-0001', 'SYN-ACT-' . $tags[$key] . '-1', '2027-01-15')],
                'restrictions' => [],
            ];
        }

        return ['format' => 1, 'hash_algorithm' => 'sha256', 'documents' => $documents];
    }

    /** Appends and activates policy rev-0002 without a new consent version (invalid on its own). */
    private static function withNewPolicyOnly(array $data): array
    {
        $data['documents'][1]['versions'][] = self::version('personal-data-policy', 'rev-0002', LegalRegistry::CONTENT_PDF, 'SYN-APR-POLICY-2', []);
        $data['documents'][1]['activations'][] = self::activation('rev-0002', 'SYN-ACT-POLICY-2', '2027-02-01');

        return $data;
    }

    /**
     * A valid update: policy rev-0002 plus consent rev-0002 linking it. Earlier records are not touched —
     * a locked version is never edited. (Single-registry validation cannot prove that across revisions.)
     */
    private static function withSecondPolicyVersion(array $data): array
    {
        $data = self::withNewPolicyOnly($data);
        $data['documents'][0]['versions'][] = self::version('inquiry-consent-text', 'rev-0002', LegalRegistry::CONTENT_HTML, 'SYN-APR-CONSENT-2', [
            ['placeholder' => 'POLICY', 'document_key' => 'personal-data-policy', 'version_id' => 'rev-0002'],
        ]);
        $data['documents'][0]['activations'][] = self::activation('rev-0002', 'SYN-ACT-CONSENT-2', '2027-02-01');

        return $data;
    }

    /** Forward activations returning both the policy and the consent to rev-0001. */
    private static function reactivateFirstVersions(array $data): array
    {
        $data['documents'][0]['activations'][] = self::activation('rev-0001', 'SYN-ACT-CONSENT-3', '2027-03-01');
        $data['documents'][1]['activations'][] = self::activation('rev-0001', 'SYN-ACT-POLICY-3', '2027-03-01');

        return $data;
    }

    private static function version(string $key, string $id, string $type, string $approval, array $links): array
    {
        return [
            'version_id' => $id,
            'locked' => true,
            'approval_reference' => $approval,
            'content_type' => $type,
            'path' => 'versions/' . $key . '/' . $id . '/' . ($type === LegalRegistry::CONTENT_HTML ? 'fragment.html' : 'document.pdf'),
            'sha256' => hash('sha256', "synthetic $key $id"),
            'bytes' => $type === LegalRegistry::CONTENT_HTML ? 900 : 1200,
            'links' => $links,
        ];
    }

    private static function activation(string $id, string $reference, string $date): array
    {
        return ['version_id' => $id, 'reference' => $reference, 'effective_from' => $date];
    }
}

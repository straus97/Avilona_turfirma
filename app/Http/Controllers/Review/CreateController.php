<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\CreateRequest;
use App\Models\ReviewConsent;
use App\Models\Reviews;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateController extends Controller
{
    public function __invoke(CreateRequest $request)
    {
        $validatedData = $request->validated();

        $userAgreementVersion = $this->documentVersion(public_path('documents/User_Agreement.pdf'));
        $personalDataConsentVersion = $this->documentVersion(resource_path('views/legal/review-personal-data-consent.blade.php'));
        $reviewPublicationConsentVersion = $this->documentVersion(resource_path('views/legal/review-publication-consent.blade.php'));

        $acceptedAt = now();

        $reviewPayloadSha256 = hash('sha256', json_encode([
            'name' => $validatedData['name'],
            'content' => $validatedData['message'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        DB::transaction(function () use (
            $validatedData,
            $userAgreementVersion,
            $personalDataConsentVersion,
            $reviewPublicationConsentVersion,
            $acceptedAt,
            $reviewPayloadSha256
        ) {
            $review = new Reviews();
            $review->name = $validatedData['name'];
            $review->title = null;
            $review->content = $validatedData['message'];
            $review->image = 0;
            $review->is_published = 0;
            $review->save();

            ReviewConsent::create([
                'review_id' => $review->id,
                'evidence_id' => (string) Str::uuid(),
                'consent_full_name' => $validatedData['consent_full_name'],
                'consent_email' => $validatedData['consent_email'],
                'user_agreement_accepted_at' => $acceptedAt,
                'personal_data_consent_accepted_at' => $acceptedAt,
                'review_publication_consent_accepted_at' => $acceptedAt,
                'user_agreement_version' => $userAgreementVersion,
                'personal_data_consent_version' => $personalDataConsentVersion,
                'review_publication_consent_version' => $reviewPublicationConsentVersion,
                'publication_scope' => ['name', 'content'],
                'publication_conditions' => $validatedData['publication_conditions'] ?? null,
                'review_payload_sha256' => $reviewPayloadSha256,
            ]);
        });

        return redirect()->route('review.index')->with('success', 'Отзыв успешно отправлен!');
    }

    private function documentVersion(string $path): string
    {
        if (! is_file($path)) {
            throw new \RuntimeException("Legal document not found for version fingerprint: {$path}");
        }

        $hash = hash_file('sha256', $path);

        if (! is_string($hash) || $hash === '') {
            throw new \RuntimeException("Failed to compute version fingerprint for: {$path}");
        }

        return 'sha256:' . $hash;
    }
}

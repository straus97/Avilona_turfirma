{{-- NOV-PUBLIC-02: описание организации (schema.org TravelAgency). Один источник —
     App\Support\Seo\PublicSeo::businessIdentity(); подключается один раз на странице.
     Только подтверждённые факты: фактический офис (не юридический адрес), e-mail,
     телефоны, ИНН. Без координат, часов работы, рейтингов, отзывов, цен, лицензий и соцсетей. --}}
<script type="application/ld+json">{!! \App\Support\Seo\PublicSeo::businessIdentityJson() !!}</script>

<div>
    <main id="contenido">
        <section class="hero" id="inicio" aria-labelledby="hero-title">
            <x-imagen-optimizada
                class="hero__image"
                :ruta="$portada->imagen ?? ''"
                :src="$portada->url_imagen"
                alt="Vista panorámica de una propiedad"
                width="1920"
                height="1080"
                :lazy="false"
                fetchpriority="high"
            />
            <div class="hero__overlay"></div>
            <div class="hero__content container">
                <p class="eyebrow">{{ $contacto->nombre_empresa }}</p>
                <h1 id="hero-title">Encuentra tu propiedad ideal</h1>
                <p>Compra, vende o alquila con los mejores asesores inmobiliarios del sur del Perú.</p>
                <a class="button button--accent" href="#propiedades">Ver propiedades</a>
            </div>
        </section>

        <section class="section {{ $tipoRanking === 'anual' ? 'section--annual-ranking' : '' }}" aria-labelledby="tops-title">
            <div class="container">
                <div class="section-heading">
                    <p class="eyebrow {{ $tipoRanking === 'anual' ? 'eyebrow--gold' : 'eyebrow--blue' }}">Nuestro equipo</p>
                    <h2 id="tops-title">
                        @if($tipoRanking === 'anual')
                            🏆 Mejores Asesores del Año {{ $rankingAnualPublicado?->anio ?? now('America/Lima')->year }}
                        @else
                            AGENTES DESTACADOS DEL MES
                        @endif
                    </h2>
                    @if($tipoRanking === 'mensual' && $rankingPublicado)
                        <p class="section-subtitle">
                            Vendedores estrella — Desempeño de {{ $rankingPublicado->etiqueta_desempeno }}
                        </p>
                    @elseif($tipoRanking === 'anual' && $rankingAnualPublicado)
                        <p class="section-subtitle">
                            Cuadro de Honor Anual — Reconocimiento al desempeño acumulado {{ $rankingAnualPublicado->anio }}
                        </p>
                    @endif

                    <div class="ranking-public-tabs" role="tablist" aria-label="Tipo de ranking destacado">
                        <button
                            type="button"
                            role="tab"
                            class="ranking-public-tab {{ $tipoRanking === 'mensual' ? 'is-active' : '' }}"
                            aria-selected="{{ $tipoRanking === 'mensual' ? 'true' : 'false' }}"
                            wire:click="cambiarTipoRanking('mensual')"
                        >
                            ★ Destacados del Mes
                        </button>
                        <button
                            type="button"
                            role="tab"
                            class="ranking-public-tab {{ $tipoRanking === 'anual' ? 'is-active' : '' }}"
                            aria-selected="{{ $tipoRanking === 'anual' ? 'true' : 'false' }}"
                            wire:click="cambiarTipoRanking('anual')"
                        >
                            🏆 Top Anual {{ $rankingAnualPublicado?->anio ?? now('America/Lima')->year }}
                        </button>
                    </div>
                </div>

                @if($tipoRanking === 'mensual')
                    @if($rankingPublicado && $rankingPublicado->posiciones->count() === 3)
                        <div class="card-grid card-grid--agents" id="featured-agents">
                            @foreach($rankingPublicado->posiciones as $posicion)
                                <article class="card agent-card">
                                    <span class="agent-badge agent-badge--{{ $posicion->puesto }}">
                                        @if($posicion->puesto === 1)
                                            1.er Puesto
                                        @elseif($posicion->puesto === 2)
                                            2.º Puesto
                                        @else
                                            3.er Puesto
                                        @endif
                                    </span>
                                    <x-imagen-optimizada
                                        :ruta="$posicion->foto_snapshot ?? ''"
                                        :src="$posicion->url_foto_snapshot"
                                        alt="{{ $posicion->nombre_completo_snapshot }}, asesor inmobiliario"
                                        width="400"
                                        height="480"
                                    />
                                    <h3>{{ $posicion->nombre_completo_snapshot }}</h3>
                                    <a
                                        href="https://api.whatsapp.com/send/?phone={{ $posicion->celular_whatsapp_snapshot }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        WhatsApp: {{ $posicion->celular_snapshot }}
                                    </a>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="ranking-updating-box" role="status">
                            Ranking mensual en actualización. Pronto conocerás a nuestros vendedores destacados del mes
                        </div>
                    @endif
                @else
                    @if($rankingAnualPublicado && $rankingAnualPublicado->posiciones->count() === 3)
                        <div class="card-grid card-grid--agents" id="featured-agents-annual">
                            @foreach($rankingAnualPublicado->posiciones as $posicion)
                                <article class="card agent-card agent-card--annual {{ $posicion->puesto === 1 ? 'is-annual-champion' : '' }}">
                                    <span class="agent-badge agent-badge--{{ $posicion->puesto }}">
                                        @if($posicion->puesto === 1)
                                            🏆 1.er Puesto Anual
                                        @elseif($posicion->puesto === 2)
                                            2.º Puesto Anual
                                        @else
                                            3.er Puesto Anual
                                        @endif
                                    </span>
                                    <x-imagen-optimizada
                                        :ruta="$posicion->foto_snapshot ?? ''"
                                        :src="$posicion->url_foto_snapshot"
                                        alt="{{ $posicion->nombre_completo_snapshot }}, asesor destacado anual"
                                        width="400"
                                        height="480"
                                    />
                                    <h3>{{ $posicion->nombre_completo_snapshot }}</h3>
                                    <a
                                        href="https://api.whatsapp.com/send/?phone={{ $posicion->celular_whatsapp_snapshot }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        WhatsApp: {{ $posicion->celular_snapshot }}
                                    </a>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="ranking-updating-box ranking-updating-box--annual" role="status">
                            Ranking anual en preparación. Pronto conocerás a los mejores asesores del año.
                        </div>
                    @endif
                @endif
            </div>
        </section>

        <section class="section section--muted" id="propiedades" aria-labelledby="properties-title">
            <div class="container container--wide">
                <div class="section-heading">
                    <p class="eyebrow eyebrow--blue">Encuentra tu próximo hogar</p>
                    <h2 id="properties-title">Propiedades destacadas</h2>
                </div>

                <div class="card-grid card-grid--properties" id="featured-properties">
                    @forelse($propiedadesDestacadas as $propiedad)
                        <article class="card property-card">
                            <div class="property-card__media">
                                <x-imagen-optimizada
                                    :ruta="$propiedad->imagen ?? ''"
                                    :src="$propiedad->url_imagen"
                                    :alt="$propiedad->nombre"
                                    width="600"
                                    height="400"
                                />
                            </div>
                            <div class="property-card__body">
                                <h3>{{ $propiedad->nombre }}</h3>
                                <p>{{ $propiedad->ubicacion }}</p>
                                @if($propiedad->precio_formateado)
                                    <p class="property-price">{{ $propiedad->precio_formateado }}</p>
                                @endif
                                <a href="{{ $propiedad->enlace_remax }}" target="_blank" rel="noopener noreferrer">
                                    Más información →
                                </a>
                            </div>
                        </article>
                    @empty
                        <p>Pronto publicaremos nuevas propiedades destacadas.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="cta" aria-labelledby="cta-title">
            <div class="container">
                <h2 id="cta-title">Trabaja con nosotros</h2>
                <p>Desarrolla tu carrera con la red RE/MAX y alcanza tu máximo potencial profesional.</p>
                <a
                    class="button button--accent"
                    href="https://api.whatsapp.com/send/?phone={{ $contacto->whatsapp_postulacion }}"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Postular por WhatsApp
                </a>
            </div>
        </section>
    </main>
</div>

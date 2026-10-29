<?php
/**
 * Automatski meta description
 *
 * Editori ne popunjavaju Yoastovo polje "Meta description", pa ga generišemo iz
 * sadržaja. Ručni unos editora ima apsolutni prioritet: čim ga editor promijeni,
 * automatika za taj zapis prestaje zauvijek — bez zastavice koju treba prebacivati.
 *
 * Kako prepoznajemo čije je: uz metadesc pamtimo MD5 zadnje vrijednosti koju smo
 * MI upisali. Ako se hash slaže, vrijednost je naša i smijemo je regenerisati.
 * Ako se ne slaže, dirao ju je editor — brišemo hash i više ne diramo.
 *
 * Hook: `wp_after_insert_post`. Jedini hook koji okida poslije Yoastovog upisa u
 * oba editora:
 *   - klasični:     wp_insert_post (Yoast metabox, p10)  ->  wp_after_insert_post
 *   - blok editor:  REST meta upis  ->  rest_after_insert_*  ->  wp_after_insert_post
 * U REST putu ga zove sam kontroler (class-wp-rest-posts-controller.php:1050), jer
 * wp_update_post() tamo dobija $fire_after_hooks = false. Odgovor se sastavlja
 * poslije toga, pa Yoastov sidebar odmah prikaže generisani opis.
 *
 * Napomena o revalidaciji: webhook u revalidation.php okida na
 * `transition_post_status` (p10), dakle PRIJE nas. To je prihvaćeno — Next.js na
 * tom pozivu samo označava keš tagove zastarjelim, a stvarno GraphQL čitanje ide
 * na sljedeći posjetiteljski zahtjev. Rizik je utrka od nekoliko milisekundi.
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;

/** Yoastovo polje za meta description. */
const HEADLESS_SEO_DESC_KEY = '_yoast_wpseo_metadesc';

/** MD5 zadnje vrijednosti koju je upisala automatika. */
const HEADLESS_SEO_HASH_KEY = '_headless_seo_metadesc_hash';

/** Gornja granica dužine opisa u znakovima. */
const HEADLESS_SEO_MAX_LEN = 155;

/** Donja granica za rez na kraju rečenice. */
const HEADLESS_SEO_MIN_LEN = 120;

/** Najkraći pasus koji prihvatamo kao izvor opisa. */
const HEADLESS_SEO_MIN_PARAGRAPH = 60;


/**
 * Tipovi sadržaja na koje se automatika primjenjuje.
 *
 * CPT-ovi `obavijest-o-smrti` i `servisne` su namjerno izostavljeni — ne žele se
 * pretraživati na Googleu, pa im meta description ne treba.
 *
 * @return string[]
 */
function headless_seo_post_types(): array {
	return [ 'post', 'page' ];
}


// ---------------------------------------------------------------------------
// Ekstrakcija
// ---------------------------------------------------------------------------

/**
 * Sastavlja meta description iz zapisa.
 *
 * Redoslijed izvora: post_excerpt -> prvi dovoljno dug pasus iz post_content.
 *
 * @param WP_Post $post Zapis.
 * @return string Opis, ili prazan string ako nema upotrebljivog izvora.
 */
function headless_seo_extract_description( WP_Post $post ): string {
	$source = headless_seo_normalize( (string) $post->post_excerpt );

	if ( $source === '' ) {
		$source = headless_seo_first_paragraph( (string) $post->post_content );
	}

	if ( $source === '' ) {
		return '';
	}

	return headless_seo_truncate( $source, HEADLESS_SEO_MAX_LEN );
}


/**
 * Vraća prvi pasus iz sadržaja koji ima najmanje HEADLESS_SEO_MIN_PARAGRAPH znakova.
 *
 * @param string $content Sirovi post_content.
 * @return string Normalizovan pasus, ili prazan string.
 */
function headless_seo_first_paragraph( string $content ): string {
	if ( trim( $content ) === '' ) {
		return '';
	}

	// Blokovski sadržaj: traži prvi core/paragraph s dovoljno teksta.
	if ( has_blocks( $content ) ) {
		$found = headless_seo_scan_blocks( parse_blocks( $content ) );

		if ( $found !== '' ) {
			return $found;
		}
		// Bez upotrebljivog core/paragraph (npr. stranica sastavljena samo od ACF
		// blokova) padamo na tekstualni pregled ispod, umjesto da vratimo prazno.
	}

	// Klasični sadržaj (ili blokovi bez pasusa).
	$plain = strip_shortcodes( $content );
	// strip_tags briše i HTML komentare, ali eksplicitno je jasnije — ne želimo da
	// JSON iz oznaka blokova (<!-- wp:acf/hero {...} /-->) procuri u opis.
	$plain = (string) preg_replace( '/<!--.*?-->/s', "\n\n", $plain );
	$plain = wp_strip_all_tags( $plain );

	foreach ( preg_split( '/\R\s*\R/u', $plain ) ?: [] as $chunk ) {
		$candidate = headless_seo_normalize( $chunk );

		if ( mb_strlen( $candidate, 'UTF-8' ) >= HEADLESS_SEO_MIN_PARAGRAPH ) {
			return $candidate;
		}
	}

	return '';
}


/**
 * Rekurzivno traži prvi core/paragraph s dovoljno teksta.
 *
 * Rekurzija je potrebna jer pasusi često žive unutar core/group, core/columns i sl.
 *
 * @param array<int, array<string, mixed>> $blocks Parsirani blokovi.
 * @return string Normalizovan pasus, ili prazan string.
 */
function headless_seo_scan_blocks( array $blocks ): string {
	foreach ( $blocks as $block ) {
		if ( ( $block['blockName'] ?? null ) === 'core/paragraph' ) {
			$candidate = headless_seo_normalize( (string) ( $block['innerHTML'] ?? '' ) );

			if ( mb_strlen( $candidate, 'UTF-8' ) >= HEADLESS_SEO_MIN_PARAGRAPH ) {
				return $candidate;
			}
		}

		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$found = headless_seo_scan_blocks( $block['innerBlocks'] );

			if ( $found !== '' ) {
				return $found;
			}
		}
	}

	return '';
}


/**
 * Svodi sirovi HTML/tekst na jednu liniju čistog teksta.
 *
 * @param string $text Sirovi tekst.
 * @return string Normalizovan tekst.
 */
function headless_seo_normalize( string $text ): string {
	// Tagovi prvo, pa entiteti — obrnuto bi &lt;script&gt; postao stvarni tag.
	$text = wp_strip_all_tags( $text );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	// &nbsp; se dekodira u U+00A0, koji \s bez PCRE_UCP ne hvata.
	$text = str_replace( "\xC2\xA0", ' ', $text );
	$text = (string) preg_replace( '/\s+/u', ' ', $text );

	return trim( $text );
}


/**
 * Skraćuje opis na najviše $max znakova.
 *
 * Prvo traži kraj rečenice u opsegu HEADLESS_SEO_MIN_LEN..$max i reže tu, bez
 * elipse. Ako ga nema, reže na granici riječi i dodaje elipsu.
 *
 * Sve mjere su u znakovima (mb_*), ne bajtovima — bosanska dijakritika (š, ć, č,
 * ž, đ) je višebajtna i obični strlen/substr bi je polomili nasred znaka.
 *
 * @param string $text Normalizovan tekst.
 * @param int    $max  Najveća dopuštena dužina u znakovima.
 * @return string Skraćeni tekst.
 */
function headless_seo_truncate( string $text, int $max ): string {
	if ( mb_strlen( $text, 'UTF-8' ) <= $max ) {
		return $text;
	}

	$window = mb_substr( $text, 0, $max, 'UTF-8' );

	// Kraj rečenice, tražen od kraja opsega prema početku.
	for ( $i = $max - 1; $i >= HEADLESS_SEO_MIN_LEN - 1; $i-- ) {
		if ( ! in_array( mb_substr( $window, $i, 1, 'UTF-8' ), [ '.', '!', '?' ], true ) ) {
			continue;
		}

		// Tačka mora zatvarati rečenicu, a ne stajati unutar broja (1.500) ili
		// skraćenice (npr.) — sljedeći znak u punom tekstu mora biti razmak.
		$next = mb_substr( $text, $i + 1, 1, 'UTF-8' );

		if ( $next === '' || $next === ' ' ) {
			return mb_substr( $window, 0, $i + 1, 'UTF-8' );
		}
	}

	// Granica riječi. Rezervišemo jedan znak za elipsu.
	$cut = mb_strrpos( $window, ' ', 0, 'UTF-8' );

	if ( $cut === false || $cut === 0 ) {
		$cut = $max - 1;
	}

	$trimmed = rtrim( mb_substr( $window, 0, $cut, 'UTF-8' ), " \t\n\r,;:.-–—" );

	if ( $trimmed === '' ) {
		$trimmed = mb_substr( $window, 0, $max - 1, 'UTF-8' );
	}

	return $trimmed . '…';
}


// ---------------------------------------------------------------------------
// Upis
// ---------------------------------------------------------------------------

/**
 * Generiše i upisuje meta description, uz očuvanje ručnog unosa editora.
 *
 * @param int     $post_id ID zapisa.
 * @param WP_Post $post    Zapis.
 * @return void
 */
function headless_seo_maybe_write_description( int $post_id, WP_Post $post ): void {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! in_array( $post->post_type, headless_seo_post_types(), true ) ) {
		return;
	}

	if ( ! in_array( $post->post_status, [ 'publish', 'future' ], true ) ) {
		return;
	}

	$current = (string) get_post_meta( $post_id, HEADLESS_SEO_DESC_KEY, true );
	$hash    = (string) get_post_meta( $post_id, HEADLESS_SEO_HASH_KEY, true );

	// Editorov unos — ne diramo ga, i odričemo se ovog zapisa zauvijek.
	if ( trim( $current ) !== '' && md5( $current ) !== $hash ) {
		if ( $hash !== '' ) {
			delete_post_meta( $post_id, HEADLESS_SEO_HASH_KEY );
		}
		return;
	}

	$generated = headless_seo_extract_description( $post );

	if ( $generated === '' || $generated === $current ) {
		return;
	}

	update_post_meta( $post_id, HEADLESS_SEO_DESC_KEY, $generated );

	// Hash pravimo od vrijednosti KAKO JE ZAPISANA, ne od one koju smo poslali.
	// Yoast na metadesc ima sanitize_callback; da hashiramo svoj ulaz, prva
	// sanitizacija bi razbila poklapanje i automatika bi se sama isključila.
	$stored = (string) get_post_meta( $post_id, HEADLESS_SEO_DESC_KEY, true );

	update_post_meta( $post_id, HEADLESS_SEO_HASH_KEY, md5( $stored ) );
}

add_action( 'wp_after_insert_post', 'headless_seo_maybe_write_description', 20, 2 );

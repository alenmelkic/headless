<?php
/**
 * Test skraćivanja opisa — dijakritika i granice.
 *
 * Pokretanje:
 *   wp eval-file scripts/test-seo-truncate.php
 * ili, bez wp-cli, PHP-om koji koristi sajt:
 *   php.exe scripts/test-seo-truncate.php
 *
 * @package Headless
 */

if ( ! defined( 'ABSPATH' ) ) {
	$root = dirname( __DIR__, 4 ); // themes/headless/scripts -> public/
	require_once $root . '/wp-load.php';
}

$fail = 0;

/**
 * Ispisuje rezultat jedne provjere.
 *
 * @param bool   $ok    Da li je provjera prošla.
 * @param string $label Opis provjere.
 * @return void
 */
function t( bool $ok, string $label ): void {
	global $fail;
	if ( ! $ok ) {
		++$fail;
	}
	printf( "  [%s] %s\n", $ok ? 'OK  ' : 'PADA', $label );
}

/**
 * Zajedničke provjere za svaki izlaz skraćivanja.
 *
 * @param string $out  Rezultat.
 * @param string $name Ime slučaja.
 * @return void
 */
function assert_sane( string $out, string $name ): void {
	$len = mb_strlen( $out, 'UTF-8' );
	t( mb_check_encoding( $out, 'UTF-8' ), "$name: validan UTF-8" );
	t( $len <= HEADLESS_SEO_MAX_LEN, "$name: mb_strlen $len <= " . HEADLESS_SEO_MAX_LEN );
	// Polomljen višebajtni znak preživi mb_check_encoding samo rijetko, pa
	// provjeravamo i povratnu konverziju: ona mijenja nevaljale bajtove.
	t(
		$out === mb_convert_encoding( $out, 'UTF-8', 'UTF-8' ),
		"$name: nema polomljenih znakova"
	);
	t( ! str_contains( $out, "\u{FFFD}" ), "$name: nema zamjenskog znaka U+FFFD" );
}

echo "=== 1. Rez pada tačno na dijakritiku ===\n";

// Niz od 200 znakova bez ijedne točke i bez razmaka blizu reza, sa šćčžđ
// rasporedenim tako da granica od 155 znakova padne na višebajtni znak.
$dia = '';
for ( $i = 0; $i < 40; $i++ ) {
	$dia .= 'ašb ćcčd žeđf '; // 14 znakova po iteraciji = 560, režemo na 200
}
$input = mb_substr( $dia, 0, 200, 'UTF-8' );

t( mb_strlen( $input, 'UTF-8' ) === 200, 'ulaz ima 200 znakova' );
t( strlen( $input ) > 200, 'ulaz je duži u bajtovima (višebajtni znakovi): ' . strlen( $input ) . ' B' );

$out = headless_seo_truncate( $input, HEADLESS_SEO_MAX_LEN );
assert_sane( $out, 'dijakritika' );
t( str_ends_with( $out, '…' ), 'dijakritika: dodana elipsa (nema kraja rečenice)' );
echo "  -> \"$out\"\n";

echo "\n=== 2. Rez na svakoj poziciji od 120 do 200 ===\n";
$broken = [];
for ( $max = 120; $max <= 200; $max++ ) {
	$o = headless_seo_truncate( $input, $max );
	if ( ! mb_check_encoding( $o, 'UTF-8' ) || $o !== mb_convert_encoding( $o, 'UTF-8', 'UTF-8' ) ) {
		$broken[] = $max;
	}
	if ( mb_strlen( $o, 'UTF-8' ) > $max ) {
		$broken[] = "$max (predugo)";
	}
}
t( $broken === [], 'sve dužine 120-200 daju validan UTF-8 i ne prelaze granicu' );
if ( $broken !== [] ) {
	echo '  pada na: ' . implode( ', ', $broken ) . "\n";
}

echo "\n=== 3. Kraj rečenice u opsegu 120-155 ===\n";
$sentence = 'Načelnik Općine Velika Kladuša najavio je nastavak radova na vodovodnoj mreži u naselju Šumatac, gdje je posao počeo prošle jeseni. Radovi se nastavljaju i tokom zime bez prekida i pauze.';
t( mb_strlen( $sentence, 'UTF-8' ) > HEADLESS_SEO_MAX_LEN, 'ulaz je duži od granice' );
$out3 = headless_seo_truncate( $sentence, HEADLESS_SEO_MAX_LEN );
assert_sane( $out3, 'rečenica' );
t( str_ends_with( $out3, '.' ), 'rečenica: rez na tački' );
t( ! str_contains( $out3, '…' ), 'rečenica: bez elipse' );
t( mb_strlen( $out3, 'UTF-8' ) >= HEADLESS_SEO_MIN_LEN, 'rečenica: rez nije prije ' . HEADLESS_SEO_MIN_LEN );
echo '  -> "' . $out3 . '" (' . mb_strlen( $out3, 'UTF-8' ) . " znakova)\n";

echo "\n=== 4. Tačka unutar broja se ne uzima kao kraj rečenice ===\n";
$num = 'Općina je za sanaciju lokalnih puteva u budžetu za ovu godinu predvidjela ukupno 1.500.000 konvertibilnih maraka, što je znatno više nego prethodnih godina u istom periodu.';
$out4 = headless_seo_truncate( $num, HEADLESS_SEO_MAX_LEN );
assert_sane( $out4, 'broj' );
t( ! preg_match( '/\d\.$/u', $out4 ), 'broj: nije rezano nasred 1.500.000' );
echo '  -> "' . $out4 . "\"\n";

echo "\n=== 5. Kratak tekst prolazi nedirnut ===\n";
$short = 'Kratka vijest iz Velike Kladuše.';
t( headless_seo_truncate( $short, HEADLESS_SEO_MAX_LEN ) === $short, 'kratak tekst nepromijenjen' );

echo "\n=== 6. Normalizacija ===\n";
$raw = "  <p>Prvi   dio&nbsp;teksta &amp; drugi\n\ndio &lt;script&gt; ovdje.</p>  ";
$norm = headless_seo_normalize( $raw );
t( $norm === 'Prvi dio teksta & drugi dio <script> ovdje.', 'normalizacija: tagovi, entiteti, nbsp, razmaci' );
echo "  -> \"$norm\"\n";

echo "\n=== 7. Jedna dugačka riječ bez razmaka ===\n";
$word = str_repeat( 'š', 300 );
$out7 = headless_seo_truncate( $word, HEADLESS_SEO_MAX_LEN );
assert_sane( $out7, 'jedna riječ' );

echo "\n";
echo $fail === 0 ? "SVE PROVJERE PROŠLE\n" : "PALO PROVJERA: $fail\n";

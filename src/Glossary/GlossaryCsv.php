<?php
/**
 * Secure glossary CSV import and export.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Glossary;

defined( 'ABSPATH' ) || exit;

/**
 * Parses CSV in memory and neutralizes spreadsheet formulas on export.
 */
final class GlossaryCsv {
	public const MAX_BYTES = 1048576;
	private const HEADERS  = array( 'term', 'target_lang', 'translation', 'rule' );
	private const MIMES    = array( 'text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel' );

	/**
	 * Constructor.
	 *
	 * @param GlossaryRepository $repository Glossary repository.
	 */
	public function __construct( private GlossaryRepository $repository ) {}

	/**
	 * Import a local CSV file without copying it to persistent storage.
	 *
	 * @param string $path Local CSV path.
	 * @return int Number of imported rules.
	 * @throws \InvalidArgumentException For an unsafe or malformed file.
	 */
	public function import( string $path ): int {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			throw new \InvalidArgumentException( 'CSV file is not readable.' );
		}
		$size = filesize( $path );
		if ( false === $size || $size > self::MAX_BYTES ) {
			throw new \InvalidArgumentException( 'CSV file exceeds 1 MB.' );
		}
		if ( 'csv' !== strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			throw new \InvalidArgumentException( 'File must use the CSV extension.' );
		}

		$finfo = new \finfo( FILEINFO_MIME_TYPE );
		$mime  = $finfo->file( $path );
		if ( ! is_string( $mime ) || ! in_array( $mime, self::MIMES, true ) ) {
			throw new \InvalidArgumentException( 'Invalid CSV MIME type.' );
		}

		// Local path validated above; remote retrieval is deliberately forbidden.
		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Safe local CSV read only.
		if ( false === $content ) {
			throw new \InvalidArgumentException( 'CSV file could not be read.' );
		}
		return $this->import_string( $content );
	}

	/**
	 * Parse and store CSV content entirely in memory.
	 *
	 * @param string $content CSV content.
	 * @return int Number of imported rules.
	 * @throws \InvalidArgumentException For invalid columns or values.
	 * @throws \RuntimeException When the memory stream cannot be created.
	 */
	public function import_string( string $content ): int {
		if ( strlen( $content ) > self::MAX_BYTES ) {
			throw new \InvalidArgumentException( 'CSV file exceeds 1 MB.' );
		}
		// php://memory guarantees that uploaded contents are never copied to disk.
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$stream = fopen( 'php://memory', 'r+' );
		if ( false === $stream ) {
			throw new \RuntimeException( 'In-memory CSV stream could not be created.' );
		}
		fwrite( $stream, $content );
		rewind( $stream );
		$headers = fgetcsv( $stream, 0, ',', '"', '' );
		if ( self::HEADERS !== $headers ) {
			fclose( $stream );
			throw new \InvalidArgumentException( 'CSV columns must be term,target_lang,translation,rule.' );
		}

		$count = 0;
		$row   = fgetcsv( $stream, 0, ',', '"', '' );
		while ( false !== $row ) {
			if ( array( null ) === $row ) {
				continue;
			}
			if ( 4 !== count( $row ) ) {
				fclose( $stream );
				throw new \InvalidArgumentException( 'Every CSV row must contain four columns.' );
			}
			$term        = sanitize_text_field( (string) $row[0] );
			$target_lang = sanitize_key( (string) $row[1] );
			$translation = sanitize_text_field( (string) $row[2] );
			$rule        = sanitize_key( (string) $row[3] );
			$this->validate_values( $term, $target_lang, $translation, $rule );
			$this->repository->add( $term, $target_lang, $translation, $rule );
			++$count;
			$row = fgetcsv( $stream, 0, ',', '"', '' );
		}
		fclose( $stream );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $count;
	}

	/**
	 * Export all rules as safe CSV.
	 *
	 * @throws \RuntimeException When the memory stream cannot be created.
	 */
	public function export(): string {
		// php://memory ensures the export never creates a server-side file.
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$stream = fopen( 'php://memory', 'r+' );
		if ( false === $stream ) {
			throw new \RuntimeException( 'In-memory CSV stream could not be created.' );
		}
		fputcsv( $stream, self::HEADERS, ',', '"', '' );
		foreach ( $this->repository->all() as $row ) {
			fputcsv(
				$stream,
				array(
					$this->safe_cell( $row['term'] ),
					$this->safe_cell( $row['target_lang'] ),
					$this->safe_cell( $row['translation'] ),
					$this->safe_cell( $row['rule'] ),
				),
				',',
				'"',
				''
			);
		}
		rewind( $stream );
		$csv = stream_get_contents( $stream );
		fclose( $stream );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return false === $csv ? '' : $csv;
	}

	/**
	 * Validate sanitized rule values.
	 *
	 * @param string $term         Source term.
	 * @param string $target_lang Target language.
	 * @param string $translation Forced translation.
	 * @param string $rule         Rule type.
	 * @throws \InvalidArgumentException For invalid values.
	 */
	public static function validate_values( string $term, string $target_lang, string $translation, string $rule ): void {
		if ( '' === $term || 1 !== preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/', $target_lang ) ) {
			throw new \InvalidArgumentException( 'Term and target language are required.' );
		}
		if ( ! in_array( $rule, array( 'translate', 'keep' ), true ) ) {
			throw new \InvalidArgumentException( 'Rule must be translate or keep.' );
		}
		if ( 'translate' === $rule && '' === $translation ) {
			throw new \InvalidArgumentException( 'Translate rules require a translation.' );
		}
	}

	/**
	 * Neutralize spreadsheet formula execution.
	 *
	 * @param string $value Cell value.
	 */
	private function safe_cell( string $value ): string {
		return 1 === preg_match( '/^[=+\-@\t\r\n]/', $value ) ? "'" . $value : $value;
	}
}

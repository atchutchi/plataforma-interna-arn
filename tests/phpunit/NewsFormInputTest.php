<?php
/** HTTP news field validation tests.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\News\NewsFormInput;
use PHPUnit\Framework\TestCase;

/** Reject malformed arrays, text and file claims before touching storage. */
final class NewsFormInputTest extends TestCase {
	/** Scalar and upload validation is strict. */
	public function test_rejects_arrays_invalid_text_and_uploads(): void {
		$base = array(
			'title'    => 'Título',
			'body'     => "Texto\nOutro",
			'category' => 'circular',
			'reason'   => 'Revisão',
		);
		$this->assertArrayHasKey( 'title', NewsFormInput::validate( array_replace( $base, array( 'title' => array( 'forjado' ) ) ), array() )['errors'] );
		$this->assertArrayHasKey( 'body', NewsFormInput::validate( array_replace( $base, array( 'body' => "Texto\x01" ) ), array() )['errors'] );
		$this->assertArrayHasKey( 'title', NewsFormInput::validate( array_replace( $base, array( 'title' => "\xFF" ) ), array() )['errors'] );
		$this->assertArrayHasKey(
			'featured',
			NewsFormInput::validate(
				array_replace(
					$base,
					array(
						'featured' => array(
							'id'          => array( 'x' ),
							'description' => 'alt',
						),
					)
				),
				array()
			)['errors']
		);
		$this->assertArrayHasKey( 'gallery', NewsFormInput::validate( array_replace( $base, array( 'gallery' => array( 2 => array( 'description' => 'alt' ) ) ) ), array() )['errors'] );
		$with_file = array_replace(
			$base,
			array(
				'featured' => array(
					'id'          => '',
					'description' => 'alt',
				),
			)
		);
		$files     = array(
			'featured_file' => array(
				'error'    => UPLOAD_ERR_OK,
				'size'     => 9,
				'tmp_name' => '/tmp/not-an-upload',
			),
		);
		$this->assertArrayHasKey( 'featured', NewsFormInput::validate( $with_file, $files )['errors'] );
		$files['featured_file']['size'] = 1048577;
		$this->assertArrayHasKey( 'featured', NewsFormInput::validate( $with_file, $files )['errors'] );
		$files['featured_file']['error'] = UPLOAD_ERR_INI_SIZE;
		$this->assertArrayHasKey( 'featured', NewsFormInput::validate( $with_file, $files )['errors'] );
	}

	/** Only editorial fields enter the draft, and order is retained. */
	public function test_rejects_extra_authority_fields_and_accepts_ordered_existing_ids(): void {
		$base = array(
			'title'    => 'Título',
			'body'     => '',
			'category' => 'circular',
			'reason'   => 'Rascunho',
			'gallery'  => array(
				array(
					'id'          => str_repeat( 'a', 32 ),
					'description' => 'A',
				),
				array(
					'id'          => str_repeat( 'b', 32 ),
					'description' => 'B',
				),
			),
		);
		$this->assertSame( array(), NewsFormInput::validate( $base, array() )['errors'] );
		$this->assertSame( str_repeat( 'b', 32 ), NewsFormInput::validate( $base, array() )['draft']->gallery[1]->existing_id );
		$this->assertArrayHasKey( 'form', NewsFormInput::validate( array_replace( $base, array( 'actor_id' => '17' ) ), array() )['errors'] );
		$this->assertArrayHasKey( 'form', NewsFormInput::validate( array_replace( $base, array( 'review_queue' => 'alheia' ) ), array() )['errors'] );
		$this->assertArrayHasKey( 'gallery', NewsFormInput::validate( array_replace( $base, array( 'gallery' => array_fill( 0, 9, $base['gallery'][0] ) ) ), array() )['errors'] );
	}
}

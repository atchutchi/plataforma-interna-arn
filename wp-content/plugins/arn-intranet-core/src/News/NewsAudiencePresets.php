<?php
/** Closed server-side editorial audience presets.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

// phpcs:disable Squiz.Commenting.FunctionComment
/** Browser values are preset names, never person or unit IDs. */
final class NewsAudiencePresets {
	/** Labels for the native selector. */
	public static function labels(): array {
		return array(
			''             => 'Seleccionar antes de enviar',
			'lab-all'      => 'Todos os leitores elegíveis',
			'lab-unit-a'   => 'Unidade LAB-A',
			'lab-unit-b'   => 'Unidade LAB-B',
			'lab-person-a' => 'Colaborador de teste A',
		);
	}

	/** Resolve the browser value only on the server. */
	public static function resolve( string $preset, ?LocalNewsRoster $roster = null ): ?Audience {
		return match ( $preset ) {
			'lab-all' => Audience::all(), 'lab-unit-a' => Audience::units( array( 'LAB-A' ) ),
			'lab-unit-b' => Audience::units( array( 'LAB-B' ) ),
			'lab-person-a' => self::personA( $roster ?? new LocalNewsRoster() ),
			default => null,
		};
	}

	/** Keep the person identifier out of browser fields. */
	private static function personA( LocalNewsRoster $roster ): ?Audience {
		$id = $roster->personAId();
		return null === $id ? null : Audience::people( array( $id ) );
	}

	/** Identify a saved audience in the closed preset list. */
	public static function selected( ?Audience $audience, ?LocalNewsRoster $roster = null ): string {
		if ( null === $audience ) {
			return '';
		}
		return match ( true ) {
			'all' === $audience->kind => 'lab-all',
			'units' === $audience->kind && array( 'LAB-A' ) === $audience->targets => 'lab-unit-a',
			'units' === $audience->kind && array( 'LAB-B' ) === $audience->targets => 'lab-unit-b',
			'people' === $audience->kind && array( ( $roster ?? new LocalNewsRoster() )->personAId() ) === $audience->targets => 'lab-person-a',
			default => '',
		};
	}

	/** Explain a saved audience to an editor. */
	public static function label( ?Audience $audience, ?LocalNewsRoster $roster = null ): string {
		if ( null === $audience ) {
			return 'Ainda sem audiência';
		}
		$preset = self::selected( $audience, $roster );
		return '' === $preset ? 'Audiência histórica sem preset' : self::labels()[ $preset ];
	}
}

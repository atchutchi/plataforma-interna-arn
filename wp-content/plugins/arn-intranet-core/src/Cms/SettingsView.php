<?php
/** Escaped, keyboard-friendly settings form and audit history.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Cms;

// phpcs:disable Squiz.Commenting
final class SettingsView {
	private const FORMATS = array(
		'application/pdf' => 'PDF',
		'image/png'       => 'PNG',
		'image/jpeg'      => 'JPEG',
	);
	public static function render( array $model ): void {
		$state    = $model['state'];
		$fields   = $model['fields'];
		$current  = $state->policy->toArray();
		$defaults = UploadPolicy::defaults()->toArray();
		echo '<div class="wrap arn-cms"><header><p class="arn-cms-context">Administração da plataforma</p><h1>Parâmetros do CMS</h1><p>Define os formatos e o tamanho máximo dos novos ficheiros. Os ficheiros já registados mantêm-se disponíveis aos seus destinatários.</p><p class="arn-cms-meta">Revisão actual: ' . esc_html( (string) $state->revision ) . '</p></header>';
		if ( $model['notice'] ) {
			echo '<p class="arn-cms-notice" role="status">Operação concluída. Uma gravação sem alterações conserva a revisão e o histórico.</p>';
		}
		if ( '' !== $model['error'] ) {
			echo '<p id="arn-cms-error" class="arn-cms-notice arn-cms-error" role="alert" tabindex="-1" autofocus>' . esc_html( $model['error'] ) . '</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=arn-cms-settings' ) ) . '" aria-label="Alterar parâmetros"><input type="hidden" name="nonce" value="' . esc_attr( wp_create_nonce( SettingsInput::nonce( get_current_user_id() ) ) ) . '"><input type="hidden" name="revision" value="' . esc_attr( (string) $state->revision ) . '">';
		foreach ( SettingsInput::LABELS as $key => $label ) {
			echo '<fieldset class="arn-cms-destination"><legend>' . esc_html( $label ) . '</legend><div class="arn-cms-row"><div><label for="' . esc_attr( $key ) . '-bytes">Tamanho máximo por ficheiro, em bytes</label><input type="number" inputmode="numeric" min="1" max="1048576" step="1" required id="' . esc_attr( $key ) . '-bytes" name="' . esc_attr( $key ) . '_bytes" value="' . esc_attr( $fields[ $key . '_bytes' ] ) . '" aria-describedby="' . esc_attr( $key ) . '-help"><p id="' . esc_attr( $key ) . '-help" class="arn-cms-help">Entre 1 e 1 048 576 bytes (1 MiB). Limites inferiores do servidor também se aplicam.</p></div><fieldset class="arn-cms-formats"><legend>Formatos permitidos</legend>';
			foreach ( $defaults[ $key ]['mimes'] as $index => $mime ) {
				echo '<label for="' . esc_attr( $key . '-mime-' . $index ) . '"><input type="checkbox" id="' . esc_attr( $key . '-mime-' . $index ) . '" name="' . esc_attr( $key ) . '_mimes[]" value="' . esc_attr( $mime ) . '" ' . checked( in_array( $mime, $fields[ $key . '_mimes' ], true ), true, false ) . '>' . esc_html( self::FORMATS[ $mime ] ) . '</label>';
			}
			echo '<p class="arn-cms-help">Escolhe pelo menos um formato.</p></fieldset></div><p class="arn-cms-current">Em vigor: ' . esc_html( self::policyText( $current[ $key ] ) ) . '</p></fieldset>';
		}
		echo '<div class="arn-cms-reason"><label for="arn-cms-reason">Motivo da alteração</label><textarea id="arn-cms-reason" name="reason" rows="3" maxlength="1000" required aria-describedby="arn-cms-reason-help">' . esc_textarea( $fields['reason'] ) . '</textarea><p id="arn-cms-reason-help" class="arn-cms-help">Explica a alteração em até 1000 caracteres. O motivo fica no histórico.</p></div><div class="arn-cms-actions"><button class="button button-primary" type="submit">Guardar parâmetros</button><a href="' . esc_url( admin_url( 'admin.php?page=arn-cms-settings' ) ) . '">Repor campos com os valores actuais</a></div></form>';
		echo '<section class="arn-cms-history" aria-labelledby="arn-cms-history-title"><h2 id="arn-cms-history-title">Histórico de alterações</h2><p>Registo de quem alterou os parâmetros, quando e porquê. Datas em UTC.</p>';
		if ( array() === $model['history'] ) {
			echo '<p>Ainda não há alterações nesta página do histórico. A primeira alteração de valores ficará registada aqui.</p>';
		}
		foreach ( $model['history'] as $event ) {
			$after = $event['after'];
			echo '<details><summary>Revisão ' . esc_html( (string) $after['revision'] ) . ' · ' . esc_html( gmdate( 'd/m/Y H:i', $after['occurred_at'] ) ) . ' UTC</summary><p>Interveniente: conta ' . esc_html( (string) $after['actor_id'] ) . '</p><p>' . nl2br( esc_html( $after['reason'] ) ) . '</p><dl>';
			foreach ( SettingsInput::LABELS as $key => $label ) {
				echo '<dt>' . esc_html( $label ) . '</dt><dd>Antes: ' . esc_html( self::policyText( $event['before']['policy'][ $key ] ) ) . '<br>Depois: ' . esc_html( self::policyText( $after['policy'][ $key ] ) ) . '</dd>';
			}
			echo '</dl></details>';
		}
		echo '<nav class="arn-cms-actions" aria-label="Páginas do histórico">';
		if ( $model['older'] ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=arn-cms-settings' ) ) . '">Alterações mais recentes</a>';
		}
		if ( 20 === count( $model['history'] ) ) {
			$last = $model['history'][19]['after']['revision'];
			if ( $last > 2 ) {
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=arn-cms-settings&before=' . $last ) ) . '">Alterações anteriores</a>';
			}
		}
		echo '</nav></section></div>';
	}
	private static function policyText( array $policy ): string {
		return number_format( $policy['max_bytes'], 0, ',', ' ' ) . ' bytes · ' . implode( ', ', array_map( static fn( string $mime ): string => self::FORMATS[ $mime ], $policy['mimes'] ) );
	}
}

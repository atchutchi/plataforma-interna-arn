<?php
/** Escaped task form primitives and labels, without authority or data access.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tasks;

// phpcs:disable Squiz.Commenting -- Rendering consumes only controller models and closed field names.
final class View {
	public const STATES     = array(
		'open'        => 'Por iniciar',
		'in_progress' => 'Em curso',
		'completed'   => 'Concluída',
		'cancelled'   => 'Cancelada',
	);
	public const PRIORITIES = array(
		'low'    => 'Baixa',
		'normal' => 'Normal',
		'high'   => 'Alta',
	);
	public const EVENTS     = array(
		'created'     => 'Tarefa atribuída',
		'started'     => 'Trabalho iniciado',
		'completed'   => 'Tarefa concluída',
		'cancelled'   => 'Tarefa cancelada',
		'reassigned'  => 'Responsável alterado',
		'due_changed' => 'Prazo alterado',
		'commented'   => 'Comentário acrescentado',
	);
	public const NOTICES    = array(
		'task_assigned'      => 'Nova atribuição',
		'task_reassigned'    => 'Atribuição alterada',
		'task_due_changed'   => 'Prazo actualizado',
		'task_commented'     => 'Novo comentário',
		'task_state_changed' => 'Estado actualizado',
	);

	public static function date( ?int $timestamp ): string {
		return null === $timestamp ? 'Sem prazo' : gmdate( 'd/m/Y H:i', $timestamp ) . ' UTC';
	}

	public static function value( array $model, string $operation, string $key, string $fallback = '' ): string {
		return ( $model['input']['operation'] ?? null ) === $operation && is_string( $model['input'][ $key ] ?? null ) ? $model['input'][ $key ] : $fallback;
	}

	private static function error( array $model, string $operation, string $key ): ?string {
		return ( $model['input']['operation'] ?? null ) === $operation && is_string( $model['errors'][ $key ] ?? null ) ? $model['errors'][ $key ] : null;
	}

	public static function hidden( array $model, string $operation, int $id = 0 ): void {
		$request = 'create' === $operation ? $model['create_request_id'] : '';
		$values  = array(
			'operation' => $operation,
			'nonce'     => wp_create_nonce( Input::nonce( $model['access']['actor_id'], $model['view'], $operation, $id, $request ) ),
		);
		if ( 'create' === $operation ) {
			$values['create_request_id'] = $request;
		} else {
			$values['id'] = (string) $id;
			if ( 'mark-read' !== $operation ) {
				$values['expected_revision'] = self::value( $model, $operation, 'expected_revision', (string) $model['data']['revision'] );
			}
		}
		foreach ( $values as $key => $value ) {
			?><input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
			<?php
		}
	}

	public static function field( array $model, string $operation, string $key, string $label, string $fallback, int $maximum, bool $required = false, bool $multiline = false, string $hint = '' ): void {
		$id        = 'arn-task-' . $operation . '-' . $key;
		$error     = self::error( $model, $operation, $key );
		$value     = self::value( $model, $operation, $key, $fallback );
		$described = trim( ( '' !== $hint ? $id . '-help ' : '' ) . ( null !== $error ? $id . '-error' : '' ) );
		?>
		<div class="arn-task-field"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
		<?php
		if ( $multiline ) {
			?>
			<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $key ); ?>" rows="4" maxlength="<?php echo esc_attr( (string) $maximum ); ?>"
			<?php
			if ( $required ) :
				?>
				required<?php endif; ?>
				<?php
				if ( null !== $error ) :
					?>
				aria-invalid="true"<?php endif; ?>
				<?php
				if ( '' !== $described ) :
					?>
	aria-describedby="<?php echo esc_attr( $described ); ?>"<?php endif; ?>><?php echo esc_textarea( $value ); ?></textarea>
			<?php
		} else {
			?>
			<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" maxlength="<?php echo esc_attr( (string) $maximum ); ?>"
			<?php
			if ( $required ) :
				?>
				required<?php endif; ?>
				<?php
				if ( null !== $error ) :
					?>
				aria-invalid="true"<?php endif; ?>
				<?php
				if ( '' !== $described ) :
					?>
	aria-describedby="<?php echo esc_attr( $described ); ?>"<?php endif; ?>>
			<?php
		}
		if ( '' !== $hint ) {
			?>
			<small id="<?php echo esc_attr( $id . '-help' ); ?>"><?php echo esc_html( $hint ); ?></small>
			<?php
		}
		if ( null !== $error ) {
			?>
			<p class="arn-task-field-error" id="<?php echo esc_attr( $id . '-error' ); ?>"><?php echo esc_html( $error ); ?></p>
			<?php
		}
		?>
		</div>
		<?php
	}

	public static function select( array $model, string $operation, string $key, string $label, array $options, string $fallback ): void {
		$id    = 'arn-task-' . $operation . '-' . $key;
		$error = self::error( $model, $operation, $key );
		$value = self::value( $model, $operation, $key, $fallback );
		?>
		<div class="arn-task-field"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label><select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $key ); ?>" required
		<?php
		if ( null !== $error ) :
			?>
			aria-invalid="true" aria-describedby="<?php echo esc_attr( $id . '-error' ); ?>"<?php endif; ?>><option value="">Selecciona</option>
		<?php
		foreach ( $options as $option => $text ) {
			?>
			<option value="<?php echo esc_attr( (string) $option ); ?>" <?php selected( $value, (string) $option ); ?>><?php echo esc_html( $text ); ?></option>
			<?php
		}
		?>
		</select>
		<?php
		if ( null !== $error ) {
			?>
			<p class="arn-task-field-error" id="<?php echo esc_attr( $id . '-error' ); ?>"><?php echo esc_html( $error ); ?></p>
			<?php
		}
		?>
		</div>
		<?php
	}
}

<?php
/**
 * Página pessoal sem menus ou fontes de módulos.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Identity\PreRegistration\PreRegistration;

if ( ! defined( 'ABSPATH' ) || ! isset( $registration ) || ! $registration instanceof PreRegistration ) {
	exit;}
?><!doctype html>
<html lang="pt-PT">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php esc_html_e( 'Acesso em preparação | ARN', 'arn-intranet' ); ?></title>
<link rel="icon" type="image/svg+xml" href="<?php echo esc_url( get_theme_file_uri( 'assets/images/favicon.svg' ) ); ?>">
<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Vista isolada antes de wp_head, sem carregar scripts, menus ou fontes de módulos. ?>
<link rel="stylesheet" href="<?php echo esc_url( get_theme_file_uri( 'assets/css/pre-registration.css' ) ); ?>">
</head>
<body class="arn-preparation">
<a class="arn-preparation-skip" href="#conteudo"><?php esc_html_e( 'Saltar para o conteúdo', 'arn-intranet' ); ?></a>
<header class="arn-preparation-header">
<img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/arn-logo.png' ) ); ?>" alt="<?php esc_attr_e( 'Autoridade Reguladora Nacional', 'arn-intranet' ); ?>" width="1491" height="684">
<span><?php esc_html_e( 'Plataforma interna', 'arn-intranet' ); ?></span>
</header>
<main id="conteudo" tabindex="-1">
<p class="arn-preparation-greeting"><?php /* translators: %s: nome da própria pessoa autenticada. */ printf( esc_html__( 'Olá, %s.', 'arn-intranet' ), esc_html( $registration->display_name ) ); ?></p>
<h1><?php esc_html_e( 'O teu acesso está em preparação.', 'arn-intranet' ); ?></h1>
<p class="arn-preparation-copy"><?php esc_html_e( 'As permissões ainda não foram atribuídas.', 'arn-intranet' ); ?></p>
<p><?php esc_html_e( 'Podes terminar a sessão e voltar a entrar mais tarde.', 'arn-intranet' ); ?></p>
<a class="arn-preparation-logout" href="<?php echo esc_url( wp_logout_url( wp_login_url() ) ); ?>"><?php esc_html_e( 'Terminar sessão', 'arn-intranet' ); ?></a>
</main>
<footer><?php esc_html_e( 'Autoridade Reguladora Nacional · Guiné-Bissau', 'arn-intranet' ); ?></footer>
</body>
</html>

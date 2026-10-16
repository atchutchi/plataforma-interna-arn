<?php
/**
 * Global presentation functions for a disposable test process.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions -- Isolated presentation doubles.
function esc_html( string $value ): string {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( string $value ): string {
	return esc_html( $value ); }
function esc_url( string $value ): string {
	return esc_html( $value ); }
function esc_textarea( string $value ): string {
	return esc_html( $value ); }
function admin_url( string $path ): string {
	return 'https://example.test/wp-admin/' . $path; }
function get_current_user_id(): int {
	return 7; }
function home_url( string $path ): string {
	return 'https://example.test' . $path; }
function get_permalink(): string {
	return 'https://example.test/comunicados/'; }
function number_format_i18n( int $number ): string {
	return (string) $number; }
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- No plain permalink in this fixture.
function arn_intranet_portal_form_route( string $url ): void {}
function wp_create_nonce( string $action ): string {
	return 'proof-' . $action; }
function add_query_arg( string|array $key, string $value, string $url = '' ): string {
	return ( is_array( $key ) ? $value : $url ) . '?' . ( is_array( $key ) ? http_build_query( $key ) : rawurlencode( $key ) . '=' . rawurlencode( $value ) ); }
function selected( string $actual, string $expected ): void {
	if ( $actual === $expected ) {
		echo 'selected="selected"'; } }

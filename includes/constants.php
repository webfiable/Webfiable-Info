<?php
/**
 * Constants: version, endpoint slug, and RSA public key.
 *
 * @package Webfiable_Info
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

// Keep version in sync with header.
define( 'WEBFIABLE_INFO_VERSION', '2.2.0' );

// Public endpoint slug.
define( 'WEBFIABLE_ENDPOINT_SLUG', 'webfiable' );

// RSA public key (hardcoded by design in this version).
define(
	'WEBFIABLE_RSA_PUBLIC_KEY',
	'-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAw8y6jWyyz5yJzdj1kdDJ
KDU54+MryJYTBHogyq8m+557Q8gciul2cAZexdhC6EkIzI/hxwNi/t6fcLiK0hdC
88nVaP6B/xkZPuURW/cjtKbCBXo0CLTMNnJSxhECI4Xq5l5koiThdhSvDlqsuMWy
xCUUlbvU9Vg+MmiaEiRtZT7Nd5/NSqftqqdiVH0Q6sUd2OEFYPwnDI5615ALLH+h
XeaQhTu053Tpqcw6cMNbqOCc9Gk6esoM69oNHtXR2tKxxzWldwb0+mRRypUiPLUn
/n/9w5jnPrNsYGu1PVLXb+wlspPyZCSItq4zkzkFPYKvQ7u+U2UY28dHqSeHJhGd
FQIDAQAB
-----END PUBLIC KEY-----'
);

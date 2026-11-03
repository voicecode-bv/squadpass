@php
    /** @var \App\Models\Club|null $club */
    $club = isset($club) && $club instanceof \App\Models\Club ? $club : null;
    $brandColor = $club?->brandPalette()[600] ?? \App\Services\BrandPalette::DEFAULT_COLOR;
@endphp
/*
 * SquadPass email theme, rendered as a view with the mail's data so a club's
 * brand colour (see BrandPalette) replaces SquadPass blue in links, buttons and
 * panels. Mails without a club keep blue. Selected via mail.markdown.theme. Mirrors the portal style: Poppins, the #f5f6fa canvas,
 * a white rounded panel, solid blue-600 as the only brand colour, ink #1a1a1a
 * for text that matters and #939393 for secondary text. No gradients or shadows
 * beyond a hairline; email clients inline these rules via CssToInlineStyles.
 */

/* Base */

body,
body *:not(html):not(style):not(br):not(tr):not(code) {
    box-sizing: border-box;
    font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    position: relative;
}

body {
    -webkit-text-size-adjust: none;
    background-color: #f5f6fa;
    color: #1a1a1a;
    height: 100%;
    line-height: 1.6;
    margin: 0;
    padding: 0;
    width: 100% !important;
}

p,
ul,
ol,
blockquote {
    line-height: 1.6;
    text-align: left;
}

a {
    color: {{ $brandColor }};
}

a img {
    border: none;
}

/* Typography */

h1 {
    color: #1a1a1a;
    font-size: 21px;
    font-weight: 600;
    line-height: 1.35;
    margin-top: 0;
    text-align: left;
}

h2 {
    color: #1a1a1a;
    font-size: 17px;
    font-weight: 600;
    margin-top: 0;
    text-align: left;
}

h3 {
    color: #1a1a1a;
    font-size: 15px;
    font-weight: 600;
    margin-top: 0;
    text-align: left;
}

p {
    color: #3f3f46;
    font-size: 15px;
    line-height: 1.6;
    margin-top: 0;
    text-align: left;
}

p.sub {
    font-size: 12px;
}

img {
    max-width: 100%;
}

strong {
    color: #1a1a1a;
    font-weight: 600;
}

/* Layout */

.wrapper {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    background-color: #f5f6fa;
    margin: 0;
    padding: 0;
    width: 100%;
}

.content {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 0;
    padding: 0;
    width: 100%;
}

/* Header: the club's email logo, its name, or the SquadPass mark */

.header {
    padding: 32px 0 24px;
    text-align: center;
}

.header a {
    text-decoration: none;
}

.club-logo {
    border: 0;
    display: inline-block;
    height: 56px;
    max-height: 56px;
    max-width: 240px;
    width: auto;
}

.club-name {
    color: #1a1a1a;
    font-size: 21px;
    font-weight: 600;
}

.brand {
    display: inline-block;
}

.brand-mark {
    border: 0;
    height: 36px;
    vertical-align: middle;
    width: 36px;
}

.brand-name {
    color: #1a1a1a;
    font-size: 21px;
    font-weight: 600;
    padding-left: 8px;
    vertical-align: middle;
}

/* Body */

.body {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    background-color: #f5f6fa;
    border-bottom: 0;
    border-top: 0;
    margin: 0;
    padding: 0;
    width: 100%;
}

.inner-body {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 570px;
    background-color: #ffffff;
    border: 1px solid #ebedf2;
    border-radius: 16px;
    margin: 0 auto;
    padding: 0;
    width: 570px;
}

.inner-body a {
    word-break: break-all;
}

/* Subcopy */

.subcopy {
    border-top: 1px solid #ebedf2;
    margin-top: 24px;
    padding-top: 24px;
}

.subcopy p {
    color: #939393;
    font-size: 13px;
}

/* Footer */

.footer {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 570px;
    margin: 0 auto;
    padding: 0;
    text-align: center;
    width: 570px;
}

.footer p {
    color: #939393;
    font-size: 12px;
    margin: 8px 0 0;
    text-align: center;
}

.footer a {
    color: #939393;
    text-decoration: underline;
}

.footer-brand {
    text-decoration: none;
}

.footer-mark {
    border: 0;
    height: 20px;
    opacity: 0.6;
    width: 20px;
}

/* Tables */

.table table {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 24px auto;
    width: 100%;
}

.table th {
    border-bottom: 1px solid #ebedf2;
    color: #939393;
    font-size: 12px;
    font-weight: 500;
    margin: 0;
    padding-bottom: 8px;
    text-transform: uppercase;
}

.table td {
    color: #1a1a1a;
    font-size: 15px;
    line-height: 1.6;
    margin: 0;
    padding: 8px 0;
}

.content-cell {
    max-width: 100vw;
    padding: 36px;
}

/* Buttons */

.action {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    float: unset;
    margin: 28px auto;
    padding: 0;
    text-align: center;
    width: 100%;
}

.button {
    -webkit-text-size-adjust: none;
    border-radius: 10px;
    color: #ffffff;
    display: inline-block;
    font-size: 15px;
    font-weight: 600;
    overflow: hidden;
    text-decoration: none;
}

.button-blue,
.button-primary {
    background-color: {{ $brandColor }};
    border-bottom: 12px solid {{ $brandColor }};
    border-left: 24px solid {{ $brandColor }};
    border-right: 24px solid {{ $brandColor }};
    border-top: 12px solid {{ $brandColor }};
}

.button-green,
.button-success {
    background-color: #16a34a;
    border-bottom: 12px solid #16a34a;
    border-left: 24px solid #16a34a;
    border-right: 24px solid #16a34a;
    border-top: 12px solid #16a34a;
}

.button-red,
.button-error {
    background-color: #dc2626;
    border-bottom: 12px solid #dc2626;
    border-left: 24px solid #dc2626;
    border-right: 24px solid #dc2626;
    border-top: 12px solid #dc2626;
}

/* Panels: a solid blue block with white text, like the portal notices */

.panel {
    border-radius: 12px;
    margin: 0 0 24px;
}

.panel-content {
    background-color: {{ $brandColor }};
    border-radius: 12px;
    color: #ffffff;
    padding: 16px 20px;
}

.panel-content p {
    color: #ffffff;
}

.panel-item {
    padding: 0;
}

.panel-item p:last-of-type {
    margin-bottom: 0;
    padding-bottom: 0;
}

/* Utilities */

.break-all {
    word-break: break-all;
}

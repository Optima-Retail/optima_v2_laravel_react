@php
$debug = false;
@endphp

<style>
    * {
        padding: 0;
        margin: 0;
    }

    /*
     | DomPDF: never wrap the whole document in one table cell (blank first page).
     | Inset via body padding. Fixed header/footer repeat on every page.
     */
    @page {
        size: A4;
        margin: 0;
    }

    body {
        margin: 0;
        /* top clears fixed header; sides/bottom match previous inset */
        padding: 130px 52px 56px 52px;
        font-family: DejaVu Sans, sans-serif;
        font-size: 11.5px;
        color: #1f2937;
        @if ($debug) background-color: red; @endif
    }

    .page-pad {
        padding: 0;
        margin: 0;
    }

    .pdf-header-fixed {
        position: fixed;
        top: 48px;
        left: 52px;
        right: 52px;
    }

    .pdf-top {
        width: 100%;
        margin: 0;
        padding: 0 0 12px 0;
        border-bottom: 1.5px solid #c5d6e8;
    }

    .pdf-top table {
        width: 100%;
        border-collapse: collapse;
    }

    .pdf-meta {
        width: 100%;
        margin: 0 0 14px 0;
    }

    .meta-table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
    }

    .meta-col {
        width: 47%;
        vertical-align: top;
    }

    .meta-gap {
        width: 6%;
    }

    .meta-kv {
        width: 100%;
        border-collapse: collapse;
    }

    .meta-kv-k {
        width: 48%;
        padding-right: 8px;
    }

    footer {
        position: fixed;
        bottom: 28px;
        left: 52px;
        right: 52px;
    }

    .footer-description {
        border-top: 1px solid #c5d6e8;
        padding-top: 6px;
        font-size: 8.5px;
        color: #5b6b7c;
        line-height: 1.3;
        text-align: center;
    }

    .pdf-legal {
        margin-top: 14px;
        font-size: 8.5px;
        color: #5b6b7c;
        line-height: 1.35;
        text-align: justify;
        page-break-inside: avoid;
    }

    section {
        padding: 9px 0;
    }

    .table-data {
        margin-top: 6px;
        width: 100%;
        font-size: 11.5px;
        border-collapse: collapse;
        border: 1px solid #d7e3ef;
    }

    .table-data tr * {
        padding: 5px 6px;
    }

    .table-data th {
        text-align: left;
        font-weight: bold;
        color: #2f4f73;
        background-color: #e8f1fa;
    }

    .table-data tr:nth-child(even) {
        background-color: #f3f6f9;
    }

    .optima-logo-cabecera {
        width: 58%;
        vertical-align: middle;
    }

    .optima-logo-cabecera img {
        width: 280px;
        height: auto;
        display: block;
    }

    .optima-info-cabecera {
        width: 42%;
        vertical-align: middle;
        text-align: right;
        font-size: 11px;
        line-height: 1.4;
        color: #1f2937;
    }

    .optima-info-cabecera p {
        margin: 0 0 2px 0;
    }

    .optima-header {
        font-weight: bold;
        font-size: 12.5px;
        color: #3b6ea5;
        border-bottom: 1.5px solid #b7cce0;
        margin: 0 0 7px 0;
        padding: 0 0 4px 0;
    }

    table {
        border-collapse: collapse;
    }

    .t-bold {
        font-weight: bold;
        color: #1f2937;
    }

    .t-small {
        font-size: 10px;
        color: #4b5563;
    }

    .t-big {
        font-size: 13px;
    }

    .t-top {
        vertical-align: top;
    }

    .t-white {
        color: #2f4f73;
    }

    .line-height-s {
        line-height: 1.4;
        color: #1f2937;
    }

    .border-top {
        border-top: 1px solid #c5d6e8;
        margin-top: 10px;
        padding-top: 10px;
    }

    .fade {
        background-color: #e8f1fa;
    }

    .description {
        margin-top: 6px;
        font-size: 11.5px;
        line-height: 1.4;
        color: #1f2937;
    }
</style>

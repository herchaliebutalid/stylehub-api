
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>StyleHub Administrator</title>

    <!--
    |--------------------------------------------------------------------------
    | STYLEHUB OFFICIAL ROUNDED FAVICON
    |--------------------------------------------------------------------------
    |
    | Uses the permanent rounded PNG icon.
    |
    | Features:
    |
    | - Equal corner radius
    | - Transparent corners
    | - Original StyleHub branding
    | - No JavaScript favicon generation
    |
    -->

    <link
        rel="icon"
        type="image/png"
        sizes="256x256"
        href="{{ asset('stylehub-favicon-rounded.png') }}?v=5"
    >

    <!--
    |--------------------------------------------------------------------------
    | STYLEHUB APPLICATION ASSETS
    |--------------------------------------------------------------------------
    -->

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])

</head>

<body>

    <div id="app"></div>

</body>

</html>

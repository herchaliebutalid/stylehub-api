
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

    <!-- Official StyleHub Mobile App Icon -->
    <link
        rel="icon"
        type="image/png"
        sizes="any"
        href="{{ asset('stylehub-icon.png') }}?v=2"
    >

    <link
        rel="shortcut icon"
        type="image/png"
        href="{{ asset('stylehub-icon.png') }}?v=2"
    >

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>

<body>
    <div id="app"></div>
</body>
</html>

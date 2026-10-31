<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Order</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
        }
        iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
</head>
<body>
    <!-- Embed the PDF in an iframe -->
    <iframe src="data:application/pdf;base64,{{ $base64Pdf }}" id="pdfFrame"></iframe>
    <script>
        // Trigger the print dialog once the iframe is loaded
        document.getElementById('pdfFrame').onload = function() {
            window.print();
        };
    </script>
</body>
</html>

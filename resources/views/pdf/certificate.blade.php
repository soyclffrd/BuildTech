<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Certificate of Completion</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; text-align: center; margin-top: 100px; }
        h1 { font-size: 28px; }
        p { font-size: 18px; }
    </style>
</head>
<body>
    <h1>Certificate of Completion</h1>
    <p>This certifies that</p>
    <h2>{{ $user->name }}</h2>
    <p>has successfully completed the course</p>
    <h3>{{ $course->title }}</h3>
    <p>Issued on {{ now()->format('F d, Y') }}</p>
</body>
</html>

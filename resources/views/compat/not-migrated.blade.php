<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Not migrated</title>
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body class="bg-background text-foreground">
    <div class="flex min-h-screen items-center justify-center p-6">
        <div class="max-w-md text-center">
            <h1 class="text-subheading font-semibold">Not migrated</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                The page <code class="rounded bg-haze px-1">{{ $page }}</code> has not been migrated yet.
            </p>
            <a href="/" class="mt-4 inline-block text-sm text-cobalt hover:underline">Go to dashboard</a>
        </div>
    </div>
</body>
</html>

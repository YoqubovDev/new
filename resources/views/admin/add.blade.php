<!DOCTYPE html>
<html>
<head>
    <title>Yangi savol</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        @if(session('success'))
            <div class="mt-2 bg-teal-100 border border-teal-200 text-sm text-teal-800 rounded-lg p-4" role="alert" tabindex="-1" aria-labelledby="hs-soft-color-success-label">
                <span id="hs-soft-color-success-label" class="font-bold">Muvoffaqiyatli!</span> {{ session('success') }}
            </div>
        @endif
        <h1 class="text-2xl font-bold mb-4">Yangi savol qo'shish</h1>
        <form action="/add" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block">Kategoriya</label>
                <select name="category" class="w-full p-2 border">
                    <option value="sport">Sport</option>
                    <option value="geography">Geografiya</option>
                    <option value="history">Tarix</option>
                    <option value="chemistry">Kimyo</option>
                    <option value="uzbekistan">O'zbekiston</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block">Savol</label>
                <textarea name="text" class="w-full p-2 border"></textarea>
            </div>
            @foreach (['a', 'b', 'c', 'd'] as $option)
                <div class="mb-4">
                    <label class="block">Variant {{ $option }}</label>
                    <input type="text" name="option_{{ $option }}" class="w-full p-2 border">
                </div>
            @endforeach
            <div class="mb-4">
                <label class="block">To'g'ri javob (a, b, c, d)</label>
                <input type="text" name="correct_answer" class="w-full p-2 border">
            </div>
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Saqlash</button>
        </form>
    </div>
</body>
</html>
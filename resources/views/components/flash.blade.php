@if (session('status'))
    <div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800">
        {{ session('status') }}
    </div>
@endif

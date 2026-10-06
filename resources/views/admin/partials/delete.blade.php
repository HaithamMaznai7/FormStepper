{{-- Delete button with confirmation. Expects $action and optional $label / $confirm. --}}
<form method="POST" action="{{ $action }}" class="inline"
      onsubmit="return confirm(@js($confirm ?? 'Delete this record? This cannot be undone.'))">
    @csrf
    @method('DELETE')
    <button type="submit" class="rounded-md border border-rose-200 bg-white px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">
        {{ $label ?? 'Delete' }}
    </button>
</form>

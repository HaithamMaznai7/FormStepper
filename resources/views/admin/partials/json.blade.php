{{-- Read-only JSON block. Expects $value. --}}
<pre class="max-h-[32rem] overflow-auto rounded-md bg-slate-900 p-4 text-xs leading-relaxed text-slate-100" dir="ltr">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>

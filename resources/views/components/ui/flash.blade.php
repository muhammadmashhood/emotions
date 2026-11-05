@if (session('status'))
    <div class="rounded-xl border border-em-ok/20 bg-em-ok-bg px-4 py-2.5 text-[13px] text-em-ok" role="status">{{ session('status') }}</div>
@endif
@if (session('error'))
    <div class="rounded-xl border border-em-bad/20 bg-em-bad-bg px-4 py-2.5 text-[13px] text-em-bad" role="alert">{{ session('error') }}</div>
@endif

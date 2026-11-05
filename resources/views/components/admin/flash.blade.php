@if (session('status'))
    <div class="rounded-xl border border-adm-ok/20 bg-adm-ok-bg px-4 py-2.5 text-[13px] text-adm-ok" role="status">{{ session('status') }}</div>
@endif
@if (session('error'))
    <div class="rounded-xl border border-adm-bad/20 bg-adm-bad-bg px-4 py-2.5 text-[13px] text-adm-bad" role="alert">{{ session('error') }}</div>
@endif

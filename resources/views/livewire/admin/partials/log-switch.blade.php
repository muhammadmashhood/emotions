<x-admin.segmented :current="$current" :items="[
    'deliveries' => ['label' => 'Webhook deliveries', 'href' => route('admin.deliveries')],
    'audit' => ['label' => 'Audit log', 'href' => route('admin.audit')],
]" />

<x-mail::message>
# You're invited to {{ $workspaceName }}

**{{ $inviterName }}** invited you to join **{{ $workspaceName }}** on Plugsent as **{{ $role }}**.

Plugsent is their self-hosted WordPress fleet manager — plugin/theme/core inventory and
updates, safely managed from one dashboard.

<x-mail::button :url="$acceptUrl">
Accept invitation
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

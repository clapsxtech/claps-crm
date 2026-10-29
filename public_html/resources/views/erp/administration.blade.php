@extends('erp.layout')
@section('content')
<div class="page-heading"><div><div class="eyebrow">SUPERADMIN</div><h1>Teams & accounts</h1><p>Create teams, onboard staff and control account access.</p></div><a class="button button-secondary" href="{{ route('erp.operations') }}">Operations & audit</a></div>
<nav class="workflow-actions" aria-label="Administration shortcuts"><a class="button" href="#action-onboard-staff">@include('erp.icon',['name'=>'plus']) Add staff</a><a class="button button-secondary" href="#accounts">Find account</a><a class="button button-secondary" href="#teams">Manage teams</a><a class="button button-secondary" href="#action-create-team">Create team</a></nav>
<details id="action-onboard-staff"><summary>Onboard staff</summary><p>Create a login and its team assignments together. No invitation email is sent.</p>
<form method="post" action="{{ route('erp.onboard') }}">@csrf
<label>Name<input name="name" value="{{ old('name') }}" minlength="3" maxlength="100" required></label>
<label>Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="off" required></label>
<label>Phone (10 digits)<input name="phone" value="{{ old('phone') }}" inputmode="numeric" pattern="[0-9]{10}" required></label>
<label>Gender<select name="gender"><option value="OTHER">Other / not specified</option><option value="FEMALE">Female</option><option value="MALE">Male</option></select></label>
<label>Password<input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
<label>Confirm password<input type="password" name="confirm_password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
<label>Initial role<select name="initial_role" required>@foreach($roles as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
<fieldset><legend>Assign teams</legend>@forelse($teams->whereNull('archived_at') as $team)<label><input type="checkbox" name="team_ids[]" value="{{ $team->id }}"> {{ $team->name }}</label>@empty<p>Create a team first.</p>@endforelse</fieldset>
<button>Create account and assign teams</button></form>
<details><summary>What each role can do</summary><ul><li>Team manager: daily operations across the assigned team; no account or team-access administration.</li><li>Academics / attendance: results, timetables, student/teacher setup and attendance review; no receipt or payout operations.</li><li>Finance: balances, receipts, payment requests, teacher payment records and student attendance imports.</li><li>Sales / follow-ups: team tasks, checklists and follow-up history.</li><li>Read-only: view team records and exports; cannot change records.</li></ul><p>Only superadmins manage team assignments and account privileges.</p></details></details>
<details id="action-create-team"><summary>Create team</summary><form method="post" action="{{ route('erp.teams.create') }}">@csrf
<label>Code<input name="code" pattern="[A-Z][A-Z0-9_-]{1,19}" placeholder="TEAM_C" required></label><label>Name<input name="name" maxlength="150" required></label><label>Timezone<input name="timezone" value="Asia/Kolkata" required></label><label>Phone (optional)<input name="phone" maxlength="30"></label><button>Create team</button></form></details>
<section id="teams"><h2>Teams</h2>@foreach($teams as $team)<details><summary>{{ $team->name }} · {{ $team->code }}{{ $team->archived_at?' · Archived':'' }}</summary>
<form method="post" action="{{ route('erp.teams.update',$team->id) }}">@csrf<label>Code<input name="code" value="{{ $team->code }}" required></label><label>Name<input name="name" value="{{ $team->name }}" required></label><label>Timezone<input name="timezone" value="{{ $team->timezone }}" required></label><label>Phone<input name="phone" value="{{ $team->phone }}"></label><button>Save team</button></form>
<p>Archiving preserves records and memberships. Staff lose access; superadmins can view the archived records. Restore the team to resume work.</p>
<form method="post" action="{{ route('erp.teams.archive',$team->id) }}">@csrf<input type="hidden" name="archived" value="{{ $team->archived_at?0:1 }}"><button>{{ $team->archived_at?'Restore team':'Archive team' }}</button></form>
@if(!$team->archived_at)<a href="{{ route('erp.business',[$team->id,'staff']) }}">Manage team assignments</a>@endif</details>@endforeach</section>
<section id="accounts"><h2>Accounts</h2><form method="get" action="{{ route('erp.administration') }}#accounts" class="search-panel"><label>Find account<input type="search" name="q" value="{{ request('q') }}" placeholder="Name or email" enterkeyhint="search"></label><button>Search</button></form>
@foreach($accounts as $account)<details><summary>{{ $account->name }} · {{ $account->email }} · {{ $account->status?'Active':'Suspended' }}{{ (new \App\Modules\IdentityAccess\Services\SiteAccessPolicy())->isAdministrator($account)?' · Superadmin':'' }}</summary>
<ul>@forelse($memberships->get($account->id,collect()) as $membership)<li>{{ $membership->name }} — {{ $roles[$membership->role] ?? $membership->role }}</li>@empty<li>No team assignments.</li>@endforelse</ul>
<form method="post" action="{{ route('erp.accounts.update',$account->id) }}">@csrf
<label>Account action<select name="action" required><option value="revoke_sessions">Sign out all sessions</option><option value="reset_password">Reset password</option><option value="{{ $account->status?'suspend':'activate' }}">{{ $account->status?'Suspend account':'Reactivate account' }}</option><option value="promote">Grant superadmin access</option>@if($account->id!==1)<option value="demote">Remove superadmin and site roles</option>@endif</select></label>
<label>New password (only for reset)<input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password"></label><label>Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password"></label>
<label>Your current password<input type="password" name="current_password" autocomplete="current-password" required></label><p>Every action signs the target account out. Removing superadmin access also removes site-level roles and direct permissions; team assignments are preserved.</p><button>Apply account action</button></form></details>@endforeach
{{ $accounts->links('pagination::simple-bootstrap-4') }}</section>
@endsection

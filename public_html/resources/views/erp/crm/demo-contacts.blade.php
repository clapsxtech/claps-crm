@php
    $pipeline = \App\Modules\Erp\Services\SalesPipeline::class;
    $parent = $pipeline::contact($demo);
    $teacherContact = $pipeline::teacherContact($demo->teacher_phone ?? null);
    $upcoming = in_array($demo->status, ['SCHEDULED','RESCHEDULED'], true);
@endphp
<div class="crm-demo-contacts" aria-label="Contact parent and teacher about this demo">
    <div class="crm-demo-contact-row"><span class="crm-demo-contact-label">Parent</span>
        @if($parent['dial'])<a class="button button-secondary" href="tel:{{ $parent['dial'] }}">@include('erp.icon',['name'=>'phone']) Call</a>@endif
        <a class="button crm-whatsapp" href="{{ $pipeline::whatsappMessage($parent['whatsapp'], $pipeline::demoParentMessage($demo,$business)) }}" target="_blank" rel="noopener noreferrer">@include('erp.icon',['name'=>'chat']) {{ $upcoming ? 'Send demo details' : 'WhatsApp' }}</a>
    </div>
    @if($demo->teacher_id)
    <div class="crm-demo-contact-row"><span class="crm-demo-contact-label">Teacher</span>
        @if($teacherContact['dial'])<a class="button button-secondary" href="tel:{{ $teacherContact['dial'] }}">@include('erp.icon',['name'=>'phone']) Call</a>
        <a class="button crm-whatsapp" href="{{ $pipeline::whatsappMessage($teacherContact['whatsapp'], $pipeline::demoTeacherMessage($demo,$business)) }}" target="_blank" rel="noopener noreferrer">@include('erp.icon',['name'=>'chat']) {{ $upcoming ? 'Send demo details' : 'WhatsApp' }}</a>
        @else<small class="crm-demo-contact-missing">No phone in the teacher's recruitment record</small>@endif
    </div>
    @endif
</div>

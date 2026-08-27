@extends('layouts.app')

@php
    $post = $post ?? null;
    $editing = $post !== null;
    $userTimezone = auth()->user()->timezone ?: config('app.timezone');
    $userNow = \Carbon\Carbon::now($userTimezone);
    $reviewPosts = collect(request('review_posts', []))->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT))->all();
    $errorField = $errors->keys()[0] ?? '';
    $initialStep = match (explode('.', $errorField)[0]) {
        'project_id' => 1,
        'platform' => 2,
        'social_page_id' => 3,
        default => $errorField ? 4 : 1,
    };
    $initialPublishes = old('publishes', [[
        'message' => old('message', $post->message ?? ''),
        'scheduled_date' => old('scheduled_date', $post?->scheduled_date?->format('Y-m-d') ?? ''),
        'scheduled_time' => old('scheduled_time', $post?->scheduled_time ? \Carbon\Carbon::parse($post->scheduled_time)->format('H:i') : ''),
        'timezone' => old('timezone', $post->timezone ?? auth()->user()->timezone ?? config('app.timezone')),
        'action' => old('action', $post?->status?->value === 'pending' ? 'schedule' : ($post?->status?->value === 'queued' ? 'publish' : 'draft')),
        'content_type' => old('content_type', ''),
    ]]);
@endphp

@section('title', $editing ? 'Edit Post' : 'Create Post')
@section('subtitle', $editing ? 'Update this post without changing the other posts in the review.' : 'Create independent posts for the selected project and destination')

@section('content')
<form method="post" action="{{ $editing ? route('posts.update', $post) : route('posts.store') }}" enctype="multipart/form-data" id="post-wizard" novalidate>
    @csrf
    @if($editing) @method('PUT') @endif
    <input type="hidden" name="platform" id="platform" value="{{ old('platform', $post->platform ?? '') }}">
    @foreach($reviewPosts as $reviewPost)<input type="hidden" name="review_posts[]" value="{{ $reviewPost }}">@endforeach

    <div class="wizard-progress mb-4">
        @foreach(['Project', 'Platform', 'Destination', 'Create Posts', 'Review'] as $number => $label)
            <div class="wizard-progress-item" data-progress="{{ $number + 1 }}"><span>{{ $number + 1 }}</span><strong>{{ $label }}</strong></div>
        @endforeach
    </div>

    <div class="wizard-shell">
        <section class="wizard-step" data-step="1">
            <div class="wizard-step-heading"><span>Step 1</span><h2>Select Project</h2><p>Choose the workspace these posts belong to.</p></div>
            <label class="form-label" for="project_id">Project <span class="required-indicator">*</span></label>
            <select class="form-select form-select-lg" name="project_id" id="project_id"><option value="">Select project</option>@foreach($projects as $item)<option value="{{ $item->id }}" @selected((int) old('project_id', $project?->id) === $item->id)>{{ $item->name }}</option>@endforeach</select>
            <div class="invalid-feedback d-block" id="project_id-error">@error('project_id'){{ $message }}@enderror</div>
        </section>

        <section class="wizard-step d-none" data-step="2">
            <div class="wizard-step-heading"><span>Step 2</span><h2>Select Platform</h2><p>Choose where these posts will appear.</p></div>
            <div class="wizard-platform-grid" id="platform-picker">@foreach(['facebook'=>'Facebook','instagram'=>'Instagram','linkedin'=>'LinkedIn','twitter'=>'X (Twitter)','tiktok'=>'TikTok','youtube'=>'YouTube'] as $key=>$name)<button type="button" class="wizard-platform-card" data-platform="{{ $key }}"><span class="platform-dot platform-{{ $key }}"></span><strong>{{ $name }}</strong><small>Publish to {{ $name }}</small></button>@endforeach</div>
            <div class="invalid-feedback d-block" id="platform-error"></div><div class="alert alert-warning d-none mt-3" id="platform-empty">No connected accounts are available for this project.</div>
        </section>

        <section class="wizard-step d-none" data-step="3">
            <div class="wizard-step-heading"><span>Step 3</span><h2>Select Page, Profile or Account</h2><p>Choose the connected destination for these posts.</p></div>
            <label class="form-label" id="destination-label" for="social_page_id">Page / Profile <span class="required-indicator">*</span></label>
            <select class="form-select form-select-lg" name="social_page_id" id="social_page_id"><option value="">Select profile/page</option>@foreach($pages as $page)<option value="{{ $page->id }}" data-provider="{{ $page->provider }}" data-instagram="{{ $page->instagram_business_id ? '1':'0' }}" data-instagram-username="{{ $page->instagram_username }}" @selected((int)old('social_page_id',$post->social_page_id ?? 0) === $page->id)>{{ ucfirst($page->provider) }} - {{ $page->page_name }}</option>@endforeach</select>
            <div class="invalid-feedback d-block" id="social_page_id-error">@error('social_page_id'){{ $message }}@enderror</div>
        </section>

        <section class="wizard-step d-none" data-step="4">
            <div class="wizard-step-heading"><span>Step 4</span><h2>Create posts</h2><p>Compose each post, attach media, then choose how it should be published.</p></div>
            <!-- <div class="timezone-context mb-4">
                <i class="bi bi-clock-history"></i>
                <div>
                    <strong>Current timezone: {{ $userTimezone }} (UTC {{ $userNow->format('P') }})</strong>
                    <span>Current local time: 
                        <time id="current-local-time">{{ $userNow->format('d M Y, h:i A') }}</time>
                    </span>
                </div>
            </div> -->
            <div id="publish-cards">
                @foreach($initialPublishes as $index => $publish)
                    @include('posts.partials.publish-card', ['index' => $index, 'publish' => $publish, 'first' => $index === 0])
                @endforeach
            </div>
            @unless($editing)<button type="button" class="btn btn-outline-primary mt-3" id="add-publish"><i class="bi bi-plus-lg"></i> Add Another Post</button>@endunless
            <datalist id="timezone-options"><option value="UTC"><option value="Asia/Kolkata"><option value="Asia/Calcutta"><option value="America/New_York"><option value="America/Los_Angeles"><option value="Europe/London"><option value="Asia/Dubai"><option value="Asia/Singapore"></datalist>
        </section>

        <div class="wizard-footer"><button type="button" class="btn btn-outline-secondary d-none" id="previous-step">Back</button><button type="button" class="btn btn-primary" id="next-step">Continue</button><button type="submit" class="btn btn-primary d-none" id="save-posts">Save Posts</button></div>
    </div>
</form>
@endsection

@push('scripts')
<script>
const form=document.querySelector('#post-wizard'),projectSelect=document.querySelector('#project_id'),platform=document.querySelector('#platform'),target=document.querySelector('#social_page_id'),cards=document.querySelector('#publish-cards');
let step={{ $initialStep }},projectPages=[],urls=[]; const userTimezone=@json($userTimezone),limits={facebook:63206,instagram:2200,linkedin:3000,twitter:280,tiktok:2200,youtube:5000};
const esc=value=>{const el=document.createElement('div');el.textContent=value||'';return el.innerHTML};
const zoneDate=zone=>{try{const p=Object.fromEntries(new Intl.DateTimeFormat('en-CA',{timeZone:zone||'UTC',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date()).filter(x=>x.type!=='literal').map(x=>[x.type,x.value]));return `${p.year}-${p.month}-${p.day}`}catch{return new Date().toISOString().slice(0,10)}};
function cardAction(card){return card.querySelector('.publish-action:checked')?.value||''}
function utcFor(date,time,zone){if(!date||!time||!zone)return null;try{const [year,month,day]=date.split('-').map(Number),[hour,minute]=time.split(':').map(Number);let utc=new Date(Date.UTC(year,month-1,day,hour,minute));for(let i=0;i<2;i++){const parts=Object.fromEntries(new Intl.DateTimeFormat('en-CA',{timeZone:zone,year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',second:'2-digit',hourCycle:'h23'}).formatToParts(utc).filter(part=>part.type!=='literal').map(part=>[part.type,part.value]));const offset=Date.UTC(+parts.year,+parts.month-1,+parts.day,+parts.hour,+parts.minute,+parts.second)-utc.getTime();utc=new Date(Date.UTC(year,month-1,day,hour,minute)-offset)}return utc}catch{return null}}
function updateUtcPreview(card){const preview=card.querySelector('.utc-preview'),date=card.querySelector('.publish-date').value,time=card.querySelector('.publish-time').value,zone=card.querySelector('.publish-timezone').value,utc=utcFor(date,time,zone);preview.classList.toggle('d-none',!utc);if(utc)preview.querySelector('.utc-value').textContent=new Intl.DateTimeFormat('en-GB',{timeZone:'UTC',day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit',hourCycle:'h23',timeZoneName:'short'}).format(utc)}
function refreshCard(card){const message=card.querySelector('.publish-message'),preview=card.querySelector('.publish-preview'),selected=target.options[target.selectedIndex],action=cardAction(card);card.querySelector('.caption-counter').textContent=`${message.value.length} / ${limits[platform.value]||63206}`;card.querySelector('.instagram-types').classList.toggle('d-none',platform.value!=='instagram');card.querySelector('.publish-schedule').classList.toggle('d-none',action!=='schedule');preview.querySelector('.platform-dot').className=`platform-dot platform-${platform.value||'facebook'}`;preview.querySelector('.preview-name').textContent=selected?.value?selected.text:'Your profile';preview.querySelector('.preview-message').textContent=message.value||'Your caption will appear here.';card.querySelector('.publish-date').min=zoneDate(card.querySelector('.publish-timezone').value);updateUtcPreview(card)}
function refreshCards(){[...cards.children].forEach((card,index)=>{card.dataset.publish=index;card.querySelectorAll('[name^="publishes["]').forEach(input=>input.name=input.name.replace(/^publishes\[\d+\]/,`publishes[${index}]`));card.querySelector('.publish-number').textContent=`Publish #${index+1}`;card.querySelector('.publish-title').textContent=`Publish #${index+1} content`;card.querySelector('.remove-publish').disabled=index===0;refreshCard(card)})}
function renderDestinations(){const instagram=platform.value==='instagram',chosen=target.value,pages=projectPages.filter(page=>page.provider===platform.value||(instagram&&page.provider==='facebook'&&page.instagram_business_id));target.innerHTML='<option value="">Select profile/page</option>'+pages.map(page=>`<option value="${page.id}">${instagram?`Facebook Page: ${esc(page.name)} → Instagram: @${esc(page.instagram_username||'Business Account')}`:esc(page.name)}</option>`).join('');if([...target.options].some(option=>option.value===chosen))target.value=chosen;document.querySelector('#destination-label').innerHTML=`${instagram?'Facebook Page':'Page / Profile'} <span class="required-indicator">*</span>`}
function renderPlatform(){const active=new Set;projectPages.forEach(page=>{active.add(page.provider);if(page.provider==='facebook'&&page.instagram_business_id)active.add('instagram')});document.querySelectorAll('.wizard-platform-card').forEach(card=>{card.classList.toggle('d-none',!active.has(card.dataset.platform));card.classList.toggle('active',card.dataset.platform===platform.value)});document.querySelector('#platform-empty').classList.toggle('d-none',active.size>0);renderDestinations();refreshCards()}
function cardMarkup(index){return document.querySelector('#publish-card-template').innerHTML.replaceAll('__INDEX__',index)}
function fieldError(card,field,message){const suffix={message:'message',action:'action',scheduled_date:'date',scheduled_time:'time',timezone:'timezone',content_type:'type',media:'media'}[field];const error=card.querySelector(`.publish-${suffix}-error`);if(error)error.textContent=message}
function validateCard(card){let valid=true;card.querySelectorAll('.invalid-feedback').forEach(el=>el.textContent='');if(!card.querySelector('.publish-message').value.trim()){fieldError(card,'message','Caption is required.');valid=false}const action=cardAction(card);if(!action){fieldError(card,'action','Choose an action.');valid=false}if(action==='schedule'){if(!card.querySelector('.publish-date').value){fieldError(card,'scheduled_date','Publish date is required.');valid=false}if(!card.querySelector('.publish-time').value){fieldError(card,'scheduled_time','Publish time is required.');valid=false}}if(platform.value==='instagram'&&!card.querySelector('[name*="content_type"]:checked')){fieldError(card,'content_type','Choose an Instagram content type.');valid=false}return valid}
const videoExtensions=['mp4','mov','avi','webm','m4v','mkv'];
const thumbnailFiles=new WeakMap();
function isVideoFile(file){return file.type.startsWith('video/')||videoExtensions.includes((file.name.split('.').pop()||'').toLowerCase())}
function setMediaFiles(input,files){const transfer=new DataTransfer();files.forEach(file=>transfer.items.add(file));input.files=transfer.files}
function thumbnailInput(card,index){return card.querySelector(`.publish-thumbnail[data-media-index="${index}"]`)}
function renderThumbnailPreview(input){const holder=input.closest('.video-thumbnail-control'),preview=holder.querySelector('.thumbnail-preview'),file=input.files[0];preview.replaceChildren();preview.classList.toggle('d-none',!file);if(!file)return;const image=document.createElement('img'),url=URL.createObjectURL(file);urls.push(url);image.src=url;image.alt='Selected video thumbnail';image.className='preview-media-file';preview.append(image)}
function renderMedia(card){const input=card.querySelector('.publish-media'),files=[...input.files],thumbs=card.querySelector('.media-thumbnails'),preview=card.querySelector('.preview-media'),publishIndex=card.dataset.publish;thumbs.replaceChildren();preview.replaceChildren();files.forEach((file,index)=>{const item=document.createElement('div');item.className='media-chip d-flex flex-wrap align-items-center gap-2 mb-2';const name=document.createElement('span');name.innerHTML=`<i class="bi bi-file-earmark"></i> ${esc(file.name)}`;item.append(name);const remove=document.createElement('button');remove.type='button';remove.className='btn btn-outline-danger btn-sm remove-media';remove.dataset.mediaIndex=index;remove.innerHTML='<i class="bi bi-x-lg"></i> Remove';item.append(remove);if(isVideoFile(file)){const control=document.createElement('div');control.className='video-thumbnail-control w-100 ms-0 ms-md-2 mt-1';const thumbnail=document.createElement('input');thumbnail.type='file';thumbnail.accept='.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp';thumbnail.className='d-none publish-thumbnail';thumbnail.dataset.mediaIndex=index;thumbnail.name=`publishes[${publishIndex}][thumbnails][${index}]`;const select=document.createElement('button');select.type='button';select.className='btn btn-outline-secondary btn-sm select-thumbnail';select.textContent='Select Thumbnail';const removeThumbnail=document.createElement('button');removeThumbnail.type='button';removeThumbnail.className='btn btn-link btn-sm text-danger remove-thumbnail d-none';removeThumbnail.textContent='Remove Thumbnail';const previewHolder=document.createElement('span');previewHolder.className='thumbnail-preview d-none';control.append(thumbnail,select,removeThumbnail,previewHolder);item.append(control)}thumbs.append(item);const media=document.createElement(isVideoFile(file)?'video':'img'),url=URL.createObjectURL(file);urls.push(url);media.src=url;media.className='preview-media-file';media.dataset.mediaIndex=index;if(media.tagName==='VIDEO')media.controls=true;preview.append(media)});const count=document.createElement('small');count.className='text-muted d-block';count.textContent=`${files.length} file${files.length===1?'':'s'} selected`;thumbs.prepend(count);preview.classList.toggle('d-none',!files.length);preview.classList.toggle('single-media',files.length===1)}
function renderMediaWithThumbnails(card){renderMedia(card);[...card.querySelector('.publish-media').files].forEach((file,index)=>{const thumbnail=thumbnailFiles.get(file),input=thumbnailInput(card,index);if(!thumbnail||!input)return;const transfer=new DataTransfer();transfer.items.add(thumbnail);input.files=transfer.files;renderThumbnailPreview(input);input.closest('.video-thumbnail-control').querySelector('.remove-thumbnail').classList.remove('d-none');const video=card.querySelector(`.preview-media video[data-media-index="${index}"]`),thumbnailUrl=URL.createObjectURL(thumbnail);urls.push(thumbnailUrl);if(video)video.poster=thumbnailUrl})}
function show(next){if(next>step&&step===1&&!projectSelect.value){document.querySelector('#project_id-error').textContent='Select a project.';return}if(next>step&&step===2&&!platform.value){document.querySelector('#platform-error').textContent='Select a platform.';return}if(next>step&&step===3&&!target.value){document.querySelector('#social_page_id-error').textContent='Select a profile or page.';return}step=next;document.querySelectorAll('.wizard-step').forEach(section=>section.classList.toggle('d-none',+section.dataset.step!==step));document.querySelectorAll('.wizard-progress-item').forEach(item=>item.classList.toggle('active',+item.dataset.progress<=step));document.querySelector('#previous-step').classList.toggle('d-none',step===1);document.querySelector('#next-step').classList.toggle('d-none',step===4);document.querySelector('#save-posts').classList.toggle('d-none',step!==4);window.scrollTo({top:0,behavior:'smooth'})}
projectPages=[...target.options].slice(1).map(option=>({id:option.value,provider:option.dataset.provider,name:option.textContent.trim(),instagram_business_id:option.dataset.instagram==='1',instagram_username:option.dataset.instagramUsername}));
document.querySelectorAll('.wizard-platform-card').forEach(card=>card.addEventListener('click',()=>{platform.value=card.dataset.platform;renderPlatform()}));projectSelect.addEventListener('change',async()=>{const response=await fetch(`{{ route('posts.pages') }}?project_id=${encodeURIComponent(projectSelect.value)}`,{headers:{Accept:'application/json'}});projectPages=response.ok?await response.json():[];renderPlatform()});target.addEventListener('change',refreshCards);document.querySelector('#add-publish')?.addEventListener('click',()=>{cards.insertAdjacentHTML('beforeend',cardMarkup(cards.children.length));refreshCards()});cards.addEventListener('input',event=>{const card=event.target.closest('.publish-card');if(card)refreshCard(card)});cards.addEventListener('change',event=>{const card=event.target.closest('.publish-card');if(!card)return;if(event.target.classList.contains('publish-media')){const files=[...event.target.files],thumbs=card.querySelector('.media-thumbnails'),preview=card.querySelector('.preview-media');thumbs.innerHTML=files.map(file=>`<span class="media-chip"><i class="bi bi-file-earmark"></i> ${esc(file.name)}</span>`).join('');preview.replaceChildren();files.forEach(file=>{const media=document.createElement(file.type.startsWith('video/')?'video':'img'),url=URL.createObjectURL(file);urls.push(url);media.src=url;media.className='preview-media-file';if(media.tagName==='VIDEO')media.controls=true;preview.append(media)});preview.classList.toggle('d-none',!files.length)}refreshCard(card)});cards.addEventListener('click',event=>{const card=event.target.closest('.publish-card');if(!card)return;if(event.target.closest('.remove-publish')&&cards.children.length>1){card.remove();refreshCards()}if(event.target.closest('.insert-tag')){const button=event.target.closest('.insert-tag'),message=card.querySelector('.publish-message');message.value+=`${message.value?' ':''}${button.dataset.insert}`;refreshCard(card)}});document.querySelector('#next-step').addEventListener('click',()=>show(step+1));document.querySelector('#previous-step').addEventListener('click',()=>show(step-1));form.addEventListener('submit',event=>{if(![...cards.children].every(validateCard)){event.preventDefault();return}});window.addEventListener('beforeunload',()=>urls.forEach(URL.revokeObjectURL));setInterval(()=>{const time=document.querySelector('#current-local-time');if(time)time.textContent=new Intl.DateTimeFormat('en-GB',{timeZone:userTimezone,day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit',hour12:true}).format(new Date())},1000);renderPlatform();show(step);
// Per-video thumbnail controls are delegated so they also work on added publish cards.
cards.addEventListener('change',event=>{const card=event.target.closest('.publish-card');if(!card)return;if(event.target.classList.contains('publish-media'))renderMediaWithThumbnails(card);if(event.target.classList.contains('publish-thumbnail')){const file=[...card.querySelector('.publish-media').files][Number(event.target.dataset.mediaIndex)];if(file&&event.target.files[0])thumbnailFiles.set(file,event.target.files[0]);renderMediaWithThumbnails(card)}});
cards.addEventListener('click',event=>{const card=event.target.closest('.publish-card');if(!card)return;const select=event.target.closest('.select-thumbnail');if(select){select.closest('.video-thumbnail-control').querySelector('.publish-thumbnail').click();return}const removeThumbnail=event.target.closest('.remove-thumbnail');if(removeThumbnail){const input=removeThumbnail.closest('.video-thumbnail-control').querySelector('.publish-thumbnail'),file=[...card.querySelector('.publish-media').files][Number(input.dataset.mediaIndex)];if(file)thumbnailFiles.delete(file);renderMediaWithThumbnails(card);return}const removeMedia=event.target.closest('.remove-media');if(removeMedia){const input=card.querySelector('.publish-media'),files=[...input.files],removed=files.splice(Number(removeMedia.dataset.mediaIndex),1)[0];if(removed)thumbnailFiles.delete(removed);setMediaFiles(input,files);renderMediaWithThumbnails(card)}});
</script>
<template id="publish-card-template">@include('posts.partials.publish-card', ['index' => '__INDEX__', 'publish' => ['action' => 'draft'], 'first' => false])</template>
@endpush

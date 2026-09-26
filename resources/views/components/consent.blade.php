@props([
    'class' => '',
])

{{--
    The consent line under a form.

    It states the agreement rather than collecting it. A required checkbox would
    mean storing a consent record with a timestamp and a version, and the project
    has no table for that, so a checkbox that is not recorded would be worse than
    saying it plainly.
--}}
<p {{ $attributes->merge(['class' => 'text-xs leading-6 text-ink-subtle '.$class]) }}>
    By clicking continue, you agree to our
    <a href="{{ route('legal.terms') }}" class="link-quiet underline hover:text-ink">Terms of Service</a>
    and
    <a href="{{ route('legal.privacy') }}" class="link-quiet underline hover:text-ink">Privacy Policy</a>.
</p>

@props([
    'course',
])

@php
    use App\Support\CourseCoverCatalog;

    $subjects = CourseCoverCatalog::SUBJECTS;
    $failed = $errors->any('cover_file') || $errors->any('cover_photo_id') || $errors->any('remove_cover');
@endphp

{{--
    Choosing, replacing and removing the cover on a Course.

    One form, one route, three answers: upload a file, pick a photograph, or take the
    cover off. The instructor changes a cover long after the course was written, so
    this lives on the course page rather than on the create form, which cannot
    preview something that does not exist yet.

    A form cannot preview an image the browser has not read, so the file input
    carries a small script that shows the chosen file and its dimensions before the
    form is submitted. It reads the file locally through the File API and never
    uploads it, which is the point: the picture is confirmed before anything is sent
    rather than after, and a file rejected by the server has not travelled anywhere.
--}}
{{--
    The attributes are merged onto this section, because a caller that hands a
    component a class has a right to expect it to be applied. The first version
    hard-coded the class and dropped everything else, so a call site adding a
    margin or a test hook was a silent no-op. The Ui\ComponentAttributesTest
    exists to catch exactly that, and it did.
--}}
<section
    id="cover"
    {{ $attributes->merge(['class' => 'mt-8 scroll-mt-24']) }}
    aria-labelledby="cover-heading"
>
    <div class="card overflow-hidden">
        <div class="border-b border-line p-5">
            <h2 id="cover-heading" class="text-lg font-semibold text-ink">Course cover</h2>
            <p class="mt-1 text-sm text-ink-muted">
                The picture students see in the catalog, on the home page, and on this
                course's own page. One image, used everywhere.
            </p>
        </div>

        {{-- What the course looks like right now, at the size it will be seen. --}}
        <div class="border-b border-line p-5">
            <x-course-cover
                :course="$course"
                :width="640"
                :height="360"
                class="aspect-[16/9] w-full max-w-md"
            />
            <p class="mt-3 text-sm text-ink-muted">
                @if ($course->hasUploadedCover())
                    This is an image you uploaded. It is served from this application.
                @elseif ($course->hasChosenCover())
                    This is a photograph from the Unsplash catalog, served from
                    Unsplash and credited to them.
                @else
                    This course has no cover, so it shows a placeholder. A course
                    without a picture is not a fault, but a list of them is harder to
                    scan.
                @endif
            </p>
        </div>

        <form
            method="POST"
            action="{{ route('instructor.courses.cover.update', $course) }}"
            enctype="multipart/form-data"
            class="space-y-6 p-5"
            data-pending
        >
            @csrf
            @method('PATCH')

            <x-form-errors :errors="$errors" />

            {{--
                The upload. Checked on the server by type read from the file, by
                size, and by real dimensions; the browser check here is only so
                somebody finds out before the upload rather than after it.
            --}}
            <div>
                <label for="cover_file" class="field-label">Upload your own image</label>
                <input
                    type="file"
                    id="cover_file"
                    name="cover_file"
                    accept="image/jpeg,image/png,image/webp"
                    class="field-control mt-1 file:mr-3 file:min-h-11 file:rounded-md file:border-0 file:bg-surface file:px-3 file:text-sm file:font-semibold file:text-ink"
                    data-cover-preview-input
                />
                <p class="mt-1.5 text-xs text-ink-subtle">
                    JPEG, PNG or WebP, at least 320 by 180 pixels, 2 MB maximum. The
                    file type is read from the image itself, not from its name.
                </p>

                {{--
                    The local preview. Empty and hidden until a file is chosen, and it
                    is a fixed box so what is shown is the shape the cover will be.
                    Nothing is sent anywhere to produce it.
                --}}
                <div
                    class="mt-3 hidden max-w-md overflow-hidden rounded-lg border border-line bg-surface-muted"
                    data-cover-preview-box
                    hidden
                >
                    <img
                        class="aspect-[16/9] w-full object-cover"
                        alt=""
                        data-cover-preview-image
                    />
                    <p class="px-3 py-2 text-xs text-ink-subtle" data-cover-preview-note></p>
                </div>
            </div>

            <div class="border-t border-line pt-6">
                <p class="field-label">Or choose a photograph</p>
                <p class="mt-1 text-xs text-ink-subtle">
                    Every one of these is a photograph of computing, a classroom or
                    people working, served from Unsplash and credited to them. They are
                    not chosen at random: each is a picture of something a computing
                    course is actually about, and a photograph unrelated to the subject
                    is worse than no photograph at all.
                </p>

                @foreach ($subjects as $subject => $photographs)
                    <fieldset class="mt-4">
                        <legend class="text-sm font-semibold text-ink">{{ $subject }}</legend>

                        <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                            @foreach ($photographs as [$identifier, $shows])
                                {{--
                                    A radio rather than a button, so the choice is part
                                    of this form and is submitted with it. A grid of
                                    buttons that each posted on their own would be three
                                    forms and three ways to lose the other answers.

                                    The peer class is what makes exactly one of them
                                    selectable as a set, which is why it is here and not
                                    only on the input.
                                --}}
                                <label class="group relative block cursor-pointer">
                                    <input
                                        type="radio"
                                        name="cover_photo_id"
                                        value="{{ $identifier }}"
                                        class="peer sr-only"
                                        @checked(old('cover_photo_id') === $identifier)
                                        data-cover-choice
                                    />
                                    <img
                                        src="{{ \App\Support\CourseCoverCatalog::urlFor($identifier, 320, 180, 60) }}"
                                        alt="{{ $shows }}"
                                        width="320"
                                        height="180"
                                        loading="lazy"
                                        decoding="async"
                                        class="aspect-[16/9] w-full rounded-lg object-cover ring-2 ring-transparent ring-offset-2 ring-offset-surface transition group-hover:opacity-90 peer-checked:ring-primary peer-focus-visible:ring-focus"
                                    />
                                    <span class="mt-1 block text-xs leading-snug text-ink-muted peer-checked:font-semibold peer-checked:text-ink">
                                        {{ $shows }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>

            @if ($course->hasCover())
                {{--
                    A closing note rather than a control.

                    This used to be a second "Remove the cover" heading and paragraph
                    inside the save form, with nothing under it to click. The control
                    that actually removes a cover is the separate form below, which
                    has to be a separate form so an accidental save cannot remove
                    something. Having the wording twice meant a person read "Remove
                    the cover" twice, once over a box with no button in it, and had no
                    way to tell which of the two was real.
                --}}
                <p class="border-t border-line pt-6 text-xs text-ink-subtle">
                    To take the cover off, use the form at the foot of this card. That
                    is a separate form on purpose, so saving a new cover can never
                    remove the old one by accident.
                </p>
            @endif

            <div class="flex flex-wrap items-center gap-3 border-t border-line pt-6">
                <x-btn type="submit" variant="primary" size="md">
                    <x-icon name="check" size="sm" />
                    Save cover
                </x-btn>

                <x-btn
                    :href="route('instructor.courses.show', $course).'#cover'"
                    variant="quiet"
                    size="md"
                >
                    Cancel
                </x-btn>
            </div>
        </form>

        {{--
            Removing a cover, as its own form.

            Its own form rather than a checkbox in the form above. Removing
            something and choosing something are different enough in consequence
            that one accidental submission should not be able to do both, and a
            separate form cannot be submitted together with the photograph picker.
        --}}
        @if ($course->hasCover())
            <form
                method="POST"
                action="{{ route('instructor.courses.cover.update', $course) }}"
                class="border-t border-line p-5"
                data-pending
                data-cover-remove-form
            >
                @csrf
                @method('PATCH')
                <input type="hidden" name="remove_cover" value="1">

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-ink-muted">
                        Remove the cover and show the placeholder instead. Nothing else
                        about the course changes, and a cover can be set again at any time.
                    </p>

                    <x-btn type="submit" variant="danger" size="sm">
                        <x-icon name="close" size="sm" />
                        Remove cover
                    </x-btn>
                </div>
            </form>
        @endif
    </div>
</section>

@push('scripts')
    <script>
        /*
         | Previewing the chosen file before the form is submitted.
         |
         | A cover is the first thing a person judges a course on, and finding out
         | after a 2 MB upload that it was the wrong photograph, or too small, or the
         | wrong shape, is a worse way to learn that than looking at it first.
         |
         | The file is read in the browser through the File API and turned into an
         | object URL. Nothing is sent: this reads a file the person has already
         | chosen, on their own machine, and revokes the URL afterwards so a preview
         | that is replaced does not keep its blob alive for the life of the page.
         |
         | The check here is a courtesy and the server is the authority. A browser can
         | be told anything, and the size and type and dimensions are all verified
         | again from the uploaded bytes before anything is stored, so this only
         | saves somebody a round trip.
         */
        (() => {
            const input = document.querySelector('[data-cover-preview-input]');
            const box = document.querySelector('[data-cover-preview-box]');
            const image = document.querySelector('[data-cover-preview-image]');
            const note = document.querySelector('[data-cover-preview-note]');

            if (!input || !box || !image || !note) return;

            const MIN_WIDTH = 320;
            const MIN_HEIGHT = 180;
            const MAX_BYTES = 2 * 1024 * 1024;
            const ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];
            const MIB = 1024 * 1024;

            let objectUrl = null;

            const reset = () => {
                if (objectUrl) {
                    URL.revokeObjectURL(objectUrl);
                    objectUrl = null;
                }

                box.hidden = true;
                box.classList.add('hidden');
                image.removeAttribute('src');
            };

            input.addEventListener('change', () => {
                reset();

                const file = input.files && input.files[0];
                if (!file) return;

                const problems = [];

                if (!ALLOWED.includes(file.type)) {
                    problems.push(`That file is ${file.type || 'of an unknown type'}. A cover must be a JPEG, PNG or WebP.`);
                }

                if (file.size > MAX_BYTES) {
                    problems.push(`That file is ${(file.size / MIB).toFixed(1)} MB. The limit is 2 MB.`);
                }

                if (problems.length === 0) {
                    const probe = new Image();

                    probe.addEventListener('load', () => {
                        if (probe.naturalWidth < MIN_WIDTH || probe.naturalHeight < MIN_HEIGHT) {
                            note.textContent = `${file.name} is ${probe.naturalWidth} by ${probe.naturalHeight}. A cover must be at least ${MIN_WIDTH} by ${MIN_HEIGHT}.`;
                            note.className = 'px-3 py-2 text-xs text-error-text';
                        } else {
                            note.textContent = `${file.name} · ${probe.naturalWidth} by ${probe.naturalHeight} · ${(file.size / 1024).toFixed(0)} KB`;
                            note.className = 'px-3 py-2 text-xs text-ink-subtle';
                        }

                        box.hidden = false;
                        box.classList.remove('hidden');
                    });

                    probe.addEventListener('error', () => {
                        note.textContent = 'That file could not be read as an image.';
                        note.className = 'px-3 py-2 text-xs text-error-text';
                        box.hidden = false;
                        box.classList.remove('hidden');
                    });

                    objectUrl = URL.createObjectURL(file);
                    image.src = objectUrl;
                } else {
                    note.textContent = problems.join(' ');
                    note.className = 'px-3 py-2 text-xs text-error-text';
                    image.removeAttribute('src');
                    box.hidden = false;
                    box.classList.remove('hidden');
                }
            });

            /*
             | A photograph chosen from the catalog replaces whatever the file input
             | was holding, and the other way round.
             |
             | The server refuses a request that carries both, which is right, but
             | refusing is a worse experience than not offering the combination: the
             | person would have to work out which of the two controls to undo. So the
             | form makes the choice visible by clearing the other one.
             */
            const form = input.closest('form');

            form?.querySelectorAll('[data-cover-choice]').forEach((choice) => {
                choice.addEventListener('change', () => {
                    if (choice.checked) {
                        input.value = '';
                        reset();
                    }
                });
            });

            input.addEventListener('change', () => {
                if (input.files && input.files.length === 0) return;

                form?.querySelectorAll('[data-cover-choice]').forEach((choice) => {
                    choice.checked = false;
                });
            });

            // Releasing the blob when the page goes, so a preview left open does not
            // hold its bytes for as long as the tab is open.
            window.addEventListener('pagehide', reset, { once: true });
        })();
    </script>
@endpush

<x-layouts.marketing
    title="Contact"
    description="Ask about a placement, a site in the network, or an existing order. We answer within one working day."
    :schema="$schema">

    <x-ui.section title="Contact us" lead="We answer within one working day, Monday to Friday.">
        <div class="grid grid-cols-1 gap-12 lg:grid-cols-3">
            <form method="post" action="{{ route('contact.store') }}" class="lg:col-span-2 flex max-w-xl flex-col gap-5">
                @csrf

                @if (session('status'))
                    <div role="status" class="rounded-card bg-success-bg p-4 text-base text-success">
                        {{ session('status') }}
                    </div>
                @endif

                @foreach ([
                    ['name', 'Your name', 'text', 'name'],
                    ['email', 'Email', 'email', 'email'],
                    ['company', 'Company (optional)', 'text', 'organization'],
                ] as [$field, $label, $type, $autocomplete])
                    <div class="flex flex-col gap-1.5">
                        <label for="{{ $field }}" class="text-sm font-medium text-ink-700">{{ $label }}</label>
                        <input id="{{ $field }}"
                               name="{{ $field }}"
                               type="{{ $type }}"
                               autocomplete="{{ $autocomplete }}"
                               value="{{ old($field) }}"
                               @if ($field !== 'company') required @endif
                               @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror
                               class="h-9 w-full rounded-input border bg-card px-3 text-base text-ink-900 placeholder:text-ink-500
                                      @error($field) border-danger @else border-subtle hover:border-strong @enderror">
                        @error($field)
                            <p id="{{ $field }}-error" role="alert" class="text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach

                <div class="flex flex-col gap-1.5">
                    <label for="message" class="text-sm font-medium text-ink-700">What do you need?</label>
                    <textarea id="message"
                              name="message"
                              rows="6"
                              required
                              @error('message') aria-invalid="true" aria-describedby="message-error" @enderror
                              class="w-full rounded-input border bg-card px-3 py-2 text-base text-ink-900 placeholder:text-ink-500
                                     @error('message') border-danger @else border-subtle hover:border-strong @enderror">{{ old('message') }}</textarea>
                    @error('message')
                        <p id="message-error" role="alert" class="text-sm text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Honeypot: a real person never fills this, a bot fills everything. --}}
                <div class="hidden" aria-hidden="true">
                    <label for="website">Leave this field empty</label>
                    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>

                <div>
                    <x-ui.button type="submit" size="lg">Send message</x-ui.button>
                </div>
            </form>

            <aside class="flex flex-col gap-6">
                <div>
                    <h2 class="font-sora text-md font-semibold text-ink-900">Email</h2>
                    <p class="mt-2 text-base text-ink-700">
                        <a href="mailto:hello@publinza.pro" class="text-brand underline">hello@publinza.pro</a>
                    </p>
                </div>
                <div>
                    <h2 class="font-sora text-md font-semibold text-ink-900">Existing orders</h2>
                    <p class="mt-2 text-base leading-relaxed text-ink-700">
                        Message us from inside the app instead — the thread is attached to the order, so
                        you do not have to explain which placement you mean.
                    </p>
                </div>
                <div>
                    <h2 class="font-sora text-md font-semibold text-ink-900">Post</h2>
                    <p class="mt-2 text-base leading-relaxed text-ink-700">
                        Publinza Media Ltd<br>
                        12 Hanover Quay<br>
                        Dublin 2, D02 K5X8<br>
                        Ireland
                    </p>
                </div>
            </aside>
        </div>
    </x-ui.section>

</x-layouts.marketing>

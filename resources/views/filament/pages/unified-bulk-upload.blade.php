<x-filament-panels::page>

    <x-filament::section>

        <x-slot name="heading">
            Unified Bulk Upload
        </x-slot>


        <p class="text-sm text-gray-500">
            Upload all customization images here.
            Filename prefix decides destination.
        </p>


    </x-filament::section>



    {{-- Every filename rule, opened with a click; the PDF is the same list to print or send to whoever exports the renders --}}
    <x-filament::section collapsible collapsed>

        <x-slot name="heading">
            Naming conventions
        </x-slot>

        <x-slot name="description">
            How to name each file so it lands in the right place.
        </x-slot>

        <x-slot name="afterHeader">
            <x-filament::link
                :href="route('admin.bulk-upload.naming-guide')"
                icon="heroicon-m-arrow-down-tray"
                size="sm"
                x-on:click.stop
            >
                Download PDF
            </x-filament::link>
        </x-slot>

        @include('filament.pages.partials.naming-guide')

    </x-filament::section>



    <x-filament::section>


        @include('filament.modals.bulk-upload-images', [
        'title' => 'Upload All Images',

        'subtitle' => 'Drag & drop images here, or click to browse',

        'processUrl' => route('admin.bulk-upload.process'),

        'priority' => \App\Services\BulkUploadService::PRIORITY,

        'accept' => 'image/*',

        ])


    </x-filament::section>


</x-filament-panels::page>

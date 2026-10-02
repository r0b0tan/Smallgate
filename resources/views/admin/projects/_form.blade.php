<div class="space-y-4">
    @if ($project->exists)
        {{-- Fixed once created: moving a project would show one customer's
             feedback to another. --}}
        <div>
            <p class="sg-label">Kunde</p>
            <p class="mt-1 sg-value">{{ $project->customer->name }}</p>
            <p class="mt-1 text-xs sg-faint">Wird beim Anlegen festgelegt und lässt sich danach nicht mehr ändern.</p>
        </div>
    @else
        <x-select name="customer_id" label="Kunde" required placeholder="Kunde auswählen"
                  :value="$project->customer_id"
                  :options="$customers->pluck('name', 'id')->all()"
                  hint="Bestimmt, welche Zugänge dieses Projekt sehen. Lässt sich danach nicht mehr ändern." />
    @endif

    <x-field name="name" label="Name" :value="$project->name" required />

    <x-field name="slug" label="Kürzel" :value="$project->slug"
             hint="Eindeutig je Kunde. Leer lassen, um es aus dem Namen abzuleiten." />

    <x-field name="description" label="Beschreibung" type="textarea" :value="$project->description"
             hint="Wird dem Kunden im Portal angezeigt." />

    <x-select name="status" label="Status" required
              :value="$project->status?->value" :options="$statuses" />
</div>

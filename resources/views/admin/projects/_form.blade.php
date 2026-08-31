<div class="space-y-4">
    <x-select name="customer_id" label="Kunde" required placeholder="Kunde auswählen"
              :value="$project->customer_id"
              :options="$customers->pluck('name', 'id')->all()"
              hint="Bestimmt, welche Zugänge dieses Projekt sehen." />

    <x-field name="name" label="Name" :value="$project->name" required />

    <x-field name="slug" label="Kürzel" :value="$project->slug"
             hint="Eindeutig je Kunde. Leer lassen, um es aus dem Namen abzuleiten." />

    <x-field name="description" label="Beschreibung" type="textarea" :value="$project->description"
             hint="Wird dem Kunden im Portal angezeigt." />

    <x-select name="status" label="Status" required
              :value="$project->status?->value" :options="$statuses" />
</div>

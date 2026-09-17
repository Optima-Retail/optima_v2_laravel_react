<x-mail::message>
# Form completed

**{{ $form->name ?: $form->subjectLabel() }}** has reached its final status ({{ $form->status?->name }}).

The completed report is attached as a PDF.

<x-mail::button :url="$url">
View form
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

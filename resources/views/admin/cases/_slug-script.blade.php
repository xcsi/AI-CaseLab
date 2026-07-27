@php
    $autoSlugDefault = $autoSlugDefault ?? (($case ?? null) ? 'false' : 'true');
@endphp

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const titleField = document.getElementById('{{ $titleId }}');
        const slugField = document.getElementById('{{ $slugId }}');
        slugField.dataset.autoSlug = slugField.dataset.autoSlug ?? '{{ $autoSlugDefault }}';

        titleField.addEventListener('input', function (event) {
            if (slugField.dataset.autoSlug !== 'false') {
                slugField.value = event.target.value
                    .toLowerCase()
                    .trim()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');
            }
        });

        slugField.addEventListener('input', function () {
            this.dataset.autoSlug = 'false';
        });
    });
</script>

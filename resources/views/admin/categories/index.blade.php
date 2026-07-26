<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fs-4 fw-semibold mb-0">Categories</h2>
            @can('create', App\Models\Category::class)
                <button type="button" class="btn btn-primary" onclick="openCreateCategoryModal()">
                    Add Category
                </button>
            @endcan
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any() && ! old('id'))
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @error('category')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th># Cases</th>
                            @can('create', App\Models\Category::class)
                                <th class="text-end">Actions</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td>{{ $category->name }}</td>
                                <td><code>{{ $category->slug }}</code></td>
                                <td>{{ $category->cases_count }}</td>
                                @can('update', $category)
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                            onclick="openEditCategoryModal({{ $category->id }}, @js($category->name), @js($category->slug), @js($category->description))">
                                            Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                            @if ($category->cases_count > 0) disabled title="Category has cases assigned and cannot be deleted" @endif
                                            onclick="openDeleteCategoryModal({{ $category->id }}, @js($category->name))">
                                            Delete
                                        </button>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-secondary py-4">No categories yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Create/Edit modal --}}
    <div class="modal fade" id="category-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" id="category-form">
                @csrf
                <div id="category-method-field"></div>
                <input type="hidden" name="id" id="category-id" value="{{ old('id') }}">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="category-modal-title">Add Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <x-input-label for="category-name" value="Name" />
                            <x-text-input type="text" id="category-name" name="name" class="mt-1" value="{{ old('name') }}" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div class="mb-3">
                            <x-input-label for="category-slug" value="Slug" />
                            <x-text-input type="text" id="category-slug" name="slug" class="mt-1" value="{{ old('slug') }}" required />
                            <x-input-error :messages="$errors->get('slug')" class="mt-2" />
                        </div>
                        <div class="mb-3">
                            <x-input-label for="category-description" value="Description" />
                            <textarea class="form-control mt-1" id="category-description" name="description" rows="2">{{ old('description') }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>
                    </div>
                    <div class="modal-footer">
                        <x-secondary-button data-bs-dismiss="modal">Cancel</x-secondary-button>
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Delete confirmation modal --}}
    <div class="modal fade" id="delete-category-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" id="delete-category-form">
                @csrf
                @method('DELETE')
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">Delete <strong id="delete-category-name"></strong>? This cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <x-secondary-button data-bs-dismiss="modal">Cancel</x-secondary-button>
                        <x-danger-button>Delete</x-danger-button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCreateCategoryModal() {
            document.getElementById('category-modal-title').textContent = 'Add Category';
            document.getElementById('category-form').action = '{{ route('admin.categories.store') }}';
            document.getElementById('category-method-field').innerHTML = '';
            document.getElementById('category-id').value = '';
            document.getElementById('category-name').value = '';
            document.getElementById('category-slug').value = '';
            document.getElementById('category-slug').dataset.autoSlug = 'true';
            document.getElementById('category-description').value = '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('category-modal')).show();
        }

        function openEditCategoryModal(id, name, slug, description) {
            document.getElementById('category-modal-title').textContent = 'Edit Category';
            document.getElementById('category-form').action = '/admin/categories/' + id;
            document.getElementById('category-method-field').innerHTML = '<input type="hidden" name="_method" value="PUT">';
            document.getElementById('category-id').value = id;
            document.getElementById('category-name').value = name;
            document.getElementById('category-slug').value = slug;
            document.getElementById('category-slug').dataset.autoSlug = 'false';
            document.getElementById('category-description').value = description ?? '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('category-modal')).show();
        }

        function openDeleteCategoryModal(id, name) {
            document.getElementById('delete-category-form').action = '/admin/categories/' + id;
            document.getElementById('delete-category-name').textContent = name;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('delete-category-modal')).show();
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('category-name').addEventListener('input', function (event) {
                const slugField = document.getElementById('category-slug');
                if (slugField.dataset.autoSlug !== 'false') {
                    slugField.value = event.target.value
                        .toLowerCase()
                        .trim()
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-+|-+$/g, '');
                }
            });

            document.getElementById('category-slug').addEventListener('input', function () {
                this.dataset.autoSlug = 'false';
            });

            @if ($errors->any() && old('id'))
                openEditCategoryModal(
                    {{ old('id') }},
                    @js(old('name')),
                    @js(old('slug')),
                    @js(old('description')),
                );
            @elseif ($errors->any())
                document.getElementById('category-slug').dataset.autoSlug = 'false';
                bootstrap.Modal.getOrCreateInstance(document.getElementById('category-modal')).show();
            @endif
        });
    </script>
</x-admin-layout>

<div class="mb-3">
    <label for="title" class="form-label">Título</label>
    <input id="title" type="text" name="title"
           value="{{ old('title', $post->title ?? '') }}"
           class="form-control @error('title') is-invalid @enderror"
           maxlength="150" required>
    @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="body" class="form-label">Contenido</label>
    <textarea id="body" name="body" rows="8"
              class="form-control @error('body') is-invalid @enderror"
              maxlength="10000" required>{{ old('body', $post->body ?? '') }}</textarea>
    @error('body') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="status" class="form-label">Estado</label>
    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
        <option value="draft" @selected(old('status', $post->status ?? 'draft') === 'draft')>Borrador</option>
        <option value="published" @selected(old('status', $post->status ?? '') === 'published')>Publicado</option>
    </select>
    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
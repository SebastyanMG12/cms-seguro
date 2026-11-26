<div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
    <i class="bi bi-shield-exclamation fs-4 me-2 flex-shrink-0" aria-hidden="true"></i>
    <div class="small">
        <strong>REGLA CRÍTICA DE PRIVACIDAD - LEY 1581 DE 2012 Y RESOLUCIÓN 1995 DE 1999:</strong>
        Las noticias y publicaciones institucionales son de acceso público general. Queda <strong>estrictamente prohibido</strong> incluir nombres, números de identificación, diagnósticos, motivos de consulta o cualquier dato de salud o personal de pacientes y usuarios del hospital. Todo texto publicado queda registrado en la bitácora de auditoría.
    </div>
</div>

<div class="mb-3">
    <label for="title" class="form-label">Título <span class="text-danger">*</span></label>
    <input id="title" type="text" name="title"
           value="{{ old('title', $post->title ?? '') }}"
           class="form-control @error('title') is-invalid @enderror"
           maxlength="150" required placeholder="Título informativo o titular de la noticia">
    @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="body" class="form-label">Contenido Institucional <span class="text-danger">*</span></label>
    <textarea id="body" name="body" rows="8"
              class="form-control @error('body') is-invalid @enderror"
              maxlength="20000" required placeholder="Redacte el comunicado o noticia institucional...">{{ old('body', $post->body ?? '') }}</textarea>
    @error('body') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="status" class="form-label">Estado de la Publicación <span class="text-danger">*</span></label>
    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
        <option value="draft" @selected(old('status', $post->status ?? 'draft') === 'draft')>Borrador (Solo visible en administración)</option>
        @can('publish', App\Models\Post::class)
            <option value="published" @selected(old('status', $post->status ?? '') === 'published')>Publicado (Visible públicamente en /noticias)</option>
        @else
            <option value="published" disabled>Publicado (Requiere rol editor o administrador)</option>
        @endcan
    </select>
    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
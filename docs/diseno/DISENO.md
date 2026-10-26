# Sistema de Diseño y Tokens: Hospital de Ubaté

Documento de especificación visual, tokens de diseño y directrices de accesibilidad (WCAG 2.2 AA) para el sistema hospitalario de urgencias.

---

## 1. Principios de Diseño
1. **Sin emojis ni ornamentos no funcionales:** La interfaz utiliza exclusivamente iconografía lineal del set **Bootstrap Icons** instalado localmente y empaquetado vía Vite (cumpliendo CSP `default-src 'self'`).
2. **Accesibilidad WCAG 2.2 AA:**
   - Ratio de contraste mínimo de texto contra fondo: **4.5:1** (verificado en badges, textos de alerta y botones).
   - Anillos de foco visibles (`outline: 2px solid #0d5c9c; outline-offset: 2px`) en todos los elementos interactivos al navegar por teclado (`:focus-visible`).
   - Objetivos táctiles mínimos de **44px** en pantallas táctiles y dispositivos móviles (`min-height: 44px`).
   - **No dependencia exclusiva del color:** Ningún estado o clasificación clínica se comunica únicamente mediante color; todos van acompañados de su texto explícito y/o icono representativo.
3. **Densidad informativa para pantallas del personal asistencial:**
   - Tablas compactas (`.table-compact`) con padding vertical reducido para maximizar la visualización de datos sin desplazamiento innecesario.
   - Tipografía numérica tabular (`tabular-nums`, `font-feature-settings: "tnum"`) en horas, fechas y códigos de camilla/episodio para alineación columnar perfecta.
   - Acciones primarias fijas y visibles.
4. **Protección de episodios cerrados:**
   - Cuando un episodio se encuentra en estado `egresado` o `expirado`, la interfaz deshabilita toda edición ordinaria y despliega el banner informativo "Episodio cerrado (solo lectura)".
   - Las acciones no permitidas en el estado actual se deshabilitan con tooltip explicativo.

---

## 2. Tokens de Diseño (`resources/css/_tokens.scss`)

### 2.1 Paleta Institucional
| Token | Valor Hex | Uso |
| --- | --- | --- |
| `$hospital-blue-dark` | `#094067` | Títulos y encabezados de alta jerarquía |
| `$hospital-blue-primary` | `#0d5c9c` | Botones principales, enlaces activos |
| `$hospital-blue-light` | `#e7f1ff` | Fondos de selección y tarjetas sutiles |
| `$hospital-blue-border` | `#b6d4fe` | Bordes institucionales |
| `$hospital-neutral-900` | `#212529` | Texto principal (contraste > 12:1) |
| `$hospital-neutral-100` | `#f8f9fa` | Fondo global de la aplicación |

### 2.2 Clasificación de Triage (Resolución 5596 de 2015)
| Nivel | Etiqueta | Color Sólido | Fondo Tenue | Borde | Texto (>= 4.5:1) |
| --- | --- | --- | --- | --- | --- |
| **Triage I** | Resucitación | `#b30000` | `#fff0f0` | `#f5c2c7` | `#842029` |
| **Triage II** | Emergencia | `#b34700` | `#fff4e6` | `#fed7aa` | `#7a3000` |
| **Triage III** | Urgencia | `#855d00` | `#fffbe6` | `#ffe58f` | `#614500` |
| **Triage IV** | Prioritario | `#13653f` | `#e8f5e9` | `#badbcc` | `#0f5132` |
| **Triage V** | No urgente | `#084298` | `#e7f1ff` | `#b6d4fe` | `#084298` |

### 2.3 Paciente Seleccionado (Sección 18.8)
- Fondo: `$selected-patient-bg` (`#e7f1ff`)
- Borde izquierdo: `$selected-patient-border` (`#0d5c9c`, grosor 5px)
- Texto: `$selected-patient-text` (`#094067`, semibold)

### 2.4 Dimensiones y Accesibilidad
- `$touch-target-min`: `44px`
- `$focus-ring-color`: `#0d5c9c`
- `$focus-ring-width`: `2px`
- `$focus-ring-offset`: `2px`

---

## 3. Lista de Pantallas y Rutas del Sistema

| Módulo | Ruta | Vista Blade | Público / Privado | Enfoque |
| --- | --- | --- | --- | --- |
| **Pre-registro Público** | `/preregistro` | `preregistration.create` | Público | Mobile First (Touch 44px, banner signos alarma) |
| **Confirmación Pre-registro**| `/preregistro/exito/{code}` | `preregistration.success` | Público | Mobile First (Código tabular, cero datos clínicos) |
| **Censo y Ocupación** | `/hospital/ocupacion` | `occupancy.index` | Privado (`ocupacion.ver`) | Desktop First (Alta densidad, censo jerárquico) |
| **Personal y Turnos** | `/hospital/personal` | `staff.index` | Privado (`personal.gestionar`) | Desktop First (Tabla compacta, turnos) |
| **Módulo Médico (Fase 4)** | `/hospital/episodios` | `episodes.index` | Privado (Médico, Enfermero, Admisiones) | Desktop First (Filtros, paciente seleccionado azul, acciones por estado) |

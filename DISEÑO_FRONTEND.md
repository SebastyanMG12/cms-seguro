Lee primero promt maestro:
Luego el promt de modulo por modulo

1. Prompt maestro (pegar primero)
Act as a senior UX/UI designer specialized in clinical information systems
(emergency department software, electronic health records, hospital operations).
Design a complete, professional web application interface for an Emergency
Department pre-registration and patient-flow management system for a regional
hospital in Colombia (Hospital de Ubate). All visible interface text must be in
Colombian Spanish. This is an academic thesis project using fictitious data only.

PRODUCT CONTEXT
- Three audiences: (1) patients and the public (pre-registration form, mobile first),
  (2) companions of patients (read-only status tracking, mobile first),
  (3) hospital staff: physicians, nurses, admissions, coordinators, administrators,
  auditors (desktop first, dense information, used under time pressure).
- Safety-critical environment. Clarity, speed of recognition, error prevention and
  data privacy take priority over decoration.

DESIGN PRINCIPLES (mandatory)
- Follow international healthcare UX practice: clinical clarity, high information
  density for staff screens, generous spacing for public screens, predictable
  layouts, consistent patterns.
- Accessibility: WCAG 2.2 level AA. Minimum contrast 4.5:1 for text and 3:1 for
  interface components. Visible keyboard focus on every interactive element.
  Minimum touch target 44x44 px on public and companion screens. Never convey
  meaning by color alone: always pair color with a text label or an icon.
- NO emojis anywhere. NO decorative illustrations, mascots, gradients, glassmorphism,
  neon colors, heavy shadows or playful animations. Use a restrained, sober,
  institutional visual language.
- Iconography: a single consistent outline icon set (stroke 1.5 to 2 px, for example
  Material Symbols Outlined or Lucide). Icons are functional, never decorative.
- Typography: one humanist sans-serif family (Inter, Source Sans 3 or IBM Plex Sans),
  maximum two weights in body text. Scale: 12, 14, 16, 20, 24, 32 px. Body text 16 px
  on public screens and 14 px on dense staff tables. Tabular numerals for times,
  codes and counts.
- Spacing on an 8 px grid. Corner radius 4 to 8 px. Flat surfaces with 1 px borders;
  elevation only for modals and dropdowns.
- Compatible with Bootstrap 5.3 (12-column grid, standard breakpoints: 576, 768, 992,
  1200, 1400 px; components such as navbar, cards, tables, badges, modals, tabs,
  accordions, alerts, form controls, pagination, toasts). Do not depend on any
  proprietary component.
- Light theme only, with a dark-mode-ready token structure. Dense layouts must remain
  readable under hospital lighting.

COLOR SYSTEM (use these as design tokens, name them in the output)
- Primary (institutional blue): #0B5CAD; primary hover #084A8C; primary subtle #E7F0FA.
- Neutrals: text #1F2933, secondary text #52606D, border #CBD2D9, surface #FFFFFF,
  background #F5F7FA.
- Selected-patient highlight: blue #DCEBFA background with a 3 px #0B5CAD left border
  (used when a patient row or card is selected, to show whose data is on screen).
- Semantic: success #1B7F4B, warning #B26A00, danger #B42318, info #0B5CAD, each with
  a subtle background variant.
- Triage levels (Colombian Resolution 5596 of 2015, five levels), always shown as a
  badge with the roman numeral and the text label, never color only:
  I Resucitacion (red #B42318), II Emergencia (orange #C2570C),
  III Urgencia (yellow #B7791F with dark text), IV Urgencia menor (green #1B7F4B),
  V Sin urgencia (blue #0B5CAD).
- Episode status badges (text plus icon): Preregistrado, En espera, En valoracion,
  Ingresado, Egresado, Expirado.
- Bed status badges: Disponible, Ocupada, En limpieza, Mantenimiento, Fuera de
  servicio, Reservada. Use distinct icons plus text.

COMPONENT LIBRARY TO PRODUCE
Buttons (primary, secondary, tertiary, destructive, disabled, loading), text inputs,
selects, textareas, checkboxes, radio groups, toggles with explicit On/Off text,
date and time pickers, search fields, tabs, data tables (sortable, with sticky
header, row selection and dense mode), cards, badges, alerts (info, success,
warning, error), toasts, modals and confirmation dialogs, stepper, timeline,
breadcrumbs, pagination, empty states, skeleton loaders, error pages (403, 404,
419, 429, 500), form validation states (default, focus, error with message,
success, disabled, read-only).

PRIVACY AND SAFETY UI PATTERNS (mandatory)
- Sensitive data (document number, blood type, allergies, diagnosis) is masked by
  default where appropriate and clearly labeled; show a lock icon with text when a
  field is restricted.
- Destructive or irreversible actions (discharge, annulment, revoking consent) always
  use a confirmation dialog that states the consequence and requires an explicit
  action. Closed (discharged) episodes show every field as read-only with a visible
  "Episodio cerrado" banner.
- Clinical entries are immutable: show corrections as new linked entries labeled
  "Aclaracion" or "Anulacion" with author, date, time and reason. Never show an
  "edit" action on a saved clinical entry.
- Consent state is always visible and explicit, with date, who granted it and scope.
- Display author and timestamp (24-hour format, America/Bogota) on every clinical action.

OUTPUT REQUIREMENTS
- Produce desktop (1440 px), tablet (768 px) and mobile (390 px) variants where the
  module requires it (public and companion screens: mobile first).
- Name each screen clearly. Keep a consistent app shell: top bar with institution name,
  logged-in user, role badge, session timer and logout; left navigation for staff.
- Use realistic but entirely fictitious data (names like "Paciente Ejemplo", codes like
  "URG-2026-0001"). Never use real people, real documents or real phone numbers.
2. Prompts por módulo

Pega cada uno después del prompt maestro. Cada bloque genera un conjunto de pantallas.

2.1 Formulario público de pre-registro (mobile first)
Design the public Emergency Pre-registration form. Mobile first (390 px), with
desktop variant. No account required.

TOP OF PAGE
- Institution header (name and simple logo placeholder) and a prominent alert:
  "Si presenta dificultad para respirar, dolor de pecho, perdida de conciencia,
  sangrado abundante o convulsiones, acuda de inmediato a urgencias o llame a la
  linea de emergencias. Este pre-registro no reemplaza la atencion ni garantiza
  prioridad."
- Optional checklist "Signos de alarma" (informational). If any is checked, show a
  high-visibility warning banner instructing to go to emergency immediately.

FORM (grouped in labeled sections, with a progress indicator of 4 steps and a final
review step, each field with label, helper text and inline validation):
1. Datos del paciente: Nombre completo (required); Tipo de documento (optional select
   with: Cedula de ciudadania, Tarjeta de identidad, Registro civil, Cedula de
   extranjeria, Pasaporte, Permiso especial, Menor sin identificacion); Numero de
   documento (disabled until a type other than "Menor sin identificacion" is chosen,
   then required, with format hint); Telefono de contacto.
2. Motivo de la urgencia: Motivo (required, textarea with 500-character counter);
   Tipo de sangre (optional select including "No se"); Alergias a medicamentos
   (required radio: "Ninguna conocida", "No se", "Si"; when "Si", a required
   textarea appears for details); Notas adicionales (optional, 1000-character counter).
3. Acompanante (optional block, collapsible): Nombre, Telefono, Parentesco; helper text
   explaining that if any field is filled, all three become required.
4. Autorizaciones: required checkbox for personal data processing under Law 1581 of
   2012 and the privacy policy of Hospital de Ubate, with a link "Leer politica de
   privacidad"; a separate, optional checkbox to authorize sharing information with the
   companion; a short statement of data subject rights and the contact channel.
5. Revision: summary of entered data with "Editar" links, then "Enviar pre-registro".

Required/optional must be explicit on every field ("Obligatorio" or "Opcional").
Do NOT include a triage level field: add a note that triage is assigned by medical
staff on arrival.

Also design: field error states, a submission confirmation screen showing the
episode code and a short instruction ("Presente este codigo al llegar. Vigencia:
24 horas."), an expired pre-registration screen, and a rate-limit (429) message.
2.2 Acceso y seguimiento del acompañante (mobile first)
Design the Companion tracking module. Mobile first (390 px), with desktop variant.

SCREENS
1. Access screen: field "Codigo del paciente" (8 to 10 characters, monospaced, no
   ambiguous characters) and field "PIN" (numeric), button "Consultar". Helper text
   explaining the PIN is delivered in person by hospital staff. Error states: generic
   message "Los datos no coinciden" (never reveal which one failed), temporary lock
   after repeated attempts with remaining time, expired access, revoked access.
2. Tracking screen with MINIMUM data only: patient shown as first name and surname
   initial (or code "NN-..." for unidentified patients), current status badge
   (En espera, En valoracion, Ingresado, Egresado), a vertical timeline with
   timestamps for Llegada, Ingreso and Salida (pending steps shown as upcoming),
   general area ("Urgencias"), and the role of the attending team (for example
   "Enfermeria") without names. A discreet "Actualizado hace X segundos" indicator
   with a manual refresh button.
   Show a clearly styled information panel: "Por proteccion de datos personales, la
   informacion clinica solo se comparte con autorizacion del paciente."
3. Tracking screen WITH patient authorization: same layout plus an additional section
   "Informacion autorizada" listing authorized procedures and diagnosis in read-only
   cards, each with date and time, and a badge "Autorizado por el paciente" with date.
   Show a state where authorization is later revoked and the section disappears with
   an informative notice.
4. Closed state: after discharge, a notice "Acceso disponible hasta [fecha y hora]".

NEVER show on this module: document number, blood type, allergies, patient phone.
2.3 Acceso del personal: inicio de sesión y doble factor
Design the staff authentication screens (desktop and mobile):
1. Login: institutional header, email and password fields, show/hide password toggle
   with text, "Iniciar sesion" button, link "Olvido su contrasena". Generic error
   "Las credenciales proporcionadas no son validas." Rate-limit message with wait time.
   Session-expired notice. No "create account" link (accounts are created by an
   administrator).
2. Two-factor challenge: 6-digit code input with large separated digit cells and auto
   focus, helper text, a secondary option "Usar un codigo de recuperacion", a timeout
   notice (the step expires in 5 minutes), generic error state.
3. Two-factor setup (mandatory for staff): numbered steps (install authenticator app,
   scan QR, enter code), QR code with manual key fallback, then a screen showing 8
   recovery codes with warning "Guardelos ahora. No se mostraran de nuevo", a copy
   button and a download button, and a required checkbox "He guardado mis codigos".
4. Password recovery by code: request step, code entry step (with remaining time and
   resend cooldown), new password step with live strength requirements checklist
   (12 characters minimum, uppercase, lowercase, number, symbol).
2.4 Módulo médico
Design the Medical module (desktop first, 1440 px, with tablet variant). This is the
main operational screen for physicians and nurses.

LAYOUT
Two-pane master-detail. Top bar and left navigation as defined. Left pane: patient
list. Right pane: selected episode detail.

LEFT PANE: patient list
- Tabs with counters: "Pre-registrados", "En espera", "Activos", "Egresados".
- Search by name or episode code, filters by triage level, status, assigned
  professional.
- Compact rows or cards showing: episode code, name (or "NN"), age, triage badge,
  status badge, waiting time since arrival (updates visually), responsible
  professional. Sort by triage level then arrival time by default.
- The SELECTED row is highlighted in blue (token "selected-patient highlight") so it
  is always clear whose data is displayed on the right.
- Empty state and loading skeleton.

RIGHT PANE: episode detail (sticky header and tabbed content)
- Sticky patient banner: name, age, sex, episode code, triage badge, status badge,
  allergy alert shown in a high-contrast danger style whenever allergies exist,
  and the "Episodio cerrado" banner when applicable.
- State progress bar with steps: Llegada, Valoracion (triage), Ingreso, Egreso, each
  with timestamp and author. Only the actions valid for the current state are
  enabled; invalid actions are disabled with a tooltip explaining why
  (for example "Debe ingresar al paciente antes de asignar camilla").
- Primary action area: "Registrar llegada", "Asignar triage", "Confirmar ingreso",
  "Registrar egreso" (opens a dialog with discharge type: alta medica, remision, alta
  voluntaria, abandono, fallecimiento, plus required date and time and observations).
- Tabs:
  1. Resumen: patient data (document and blood type masked with a reveal action that
     is logged), contact, reason for visit, allergies, notes.
  2. Equipo asignado: responsible physician (exactly one) and support staff, each with
     role, start time, and a counter "N pacientes asignados". Assignment picker that
     lists only staff on active shift; staff on rest appear disabled with the label
     "En descanso".
  3. Ubicacion: bed assignment with a floor, room and bed selector; occupied, cleaning
     and out-of-service beds disabled with their status; show the assigned bed code
     (for example P1-H101-A).
  4. Procedimientos y diagnostico: chronological list of immutable entries with author,
     role, date and time; "Agregar entrada" form; "Agregar aclaracion" and "Anular"
     actions that require a reason and create linked entries (no edit action).
  5. Acompanante: companion data (name, phone, relationship) with "Verificado
     presencialmente por [usuario] el [fecha]", public code and PIN regeneration,
     and a "Revocar acceso" action.
  6. Consentimientos: current consent status, history timeline (granted, extended,
     revoked) and a clearly labeled toggle "Compartir diagnostico y procedimientos con
     el acompanante" that requires recording who authorized (patient or legal
     representative) and opens a confirmation dialog.
  7. Auditoria del episodio: read-only log of actions on this episode.

ADDITIONAL SCREENS
- Emergency registration for an unidentified patient: temporary code "NN-AAAAMMDD-0001",
  minimal fields (apparent sex, approximate age, visible characteristics, how they
  arrived, who brought them), banner "Atencion sin consentimiento previo: interes vital".
- Identity reconciliation: linking an NN episode to an identified patient, with a
  comparison view and a required evidence field.
- Closed episode view where every control is read-only, with an "Agregar nota
  aclaratoria" action restricted to authorized roles.
- Exceptional access dialog ("Acceso excepcional") requiring a justification.
- Shift-end alert listing patients that need reassignment.
2.5 Módulo administrador
Design the Administration module (desktop first, 1440 px, tablet variant).

SCREENS
1. Administration home: operational summary cards (beds available, occupied, in
   cleaning, out of service; staff on shift; staff on rest; episodes by status),
   with data tables beneath. No decorative charts; only charts that answer an
   operational question (for example waiting time per triage level), with accessible
   labels and a data table alternative.
2. Hospital resources: hierarchical tree (Sede, Piso, Area, Habitacion, Camilla) on the
   left; detail and editing form on the right. Bed occupancy map per floor showing each
   room with its beds as labeled tiles (code, status badge, patient code when
   occupied). Actions: create, edit, set out of service (blocked with explanatory
   message when occupied), release bed, mark as cleaned. Room capacity is configurable.
3. Staff: table with name, staff code, roles (several allowed, shown as badges),
   specialty, current shift status (En turno, Descanso), assigned patients counter,
   account status. Create/edit form with multiple role selection, deactivate (logical
   delete with reason), reset two-factor (with confirmation and reason).
4. Shifts: weekly schedule grid by staff member and a form to create shifts.
5. Roles and permissions: matrix of permissions by role with clear grouping,
   read-only for system roles, explanatory text on separation of duties
   (an administrator does not read clinical data by default).
6. Users: list, create, edit, suspend and reactivate with required reason.
7. Audit log (auditor role): table with timestamp, actor, role, action, entity,
   episode code, IP and result; filters by date range, actor, action and episode;
   export action; read-only notice "Los registros de auditoria no pueden modificarse".
   Include a "lecturas de historia clinica" view.
8. Clinical history PDF generation: preview of the report layout with a visible
   watermark (user and date), a confirmation dialog stating that the generation is
   logged, and a page showing the PDF document design itself (header with institution,
   patient and episode data, chronological entries with author and timestamps, footer
   with page number and generation data).
9. Security settings for the user: password change, two-factor status, active
   sessions with "Cerrar otras sesiones".
2.6 Estados transversales
Design the cross-cutting states and system pages consistent with the design system:
403 Acceso denegado, 404 No encontrado, 419 Sesion expirada, 429 Demasiados intentos,
500 Error del servidor (generic message, no technical details), maintenance page,
session-timeout warning modal with countdown and "Continuar sesion" action, offline or
connection-lost banner for the live tracking screens, empty states and loading
skeletons for every table and list, and a generic confirmation dialog pattern for
destructive actions.
3. Prompts de refinamiento

Úsalos cuando una pantalla generada incumpla las reglas.

Remove any emoji, decorative illustration, gradient or non-functional ornament.
Replace them with functional outline icons from a single icon set.
Improve accessibility: ensure 4.5:1 text contrast, visible focus rings, 44 px touch
targets on mobile, and never use color as the only indicator (add text labels or icons
to every status, triage level and validation state).
Increase information density for staff screens: reduce vertical padding, use a compact
table mode, keep tabular numerals for times and codes, and keep primary actions
visible without scrolling.
Keep the layout compatible with Bootstrap 5.3 components and the 12-column grid, and
express all colors, spacing and typography as named design tokens.
Show only the actions that are valid for the current episode state; disable the rest
with an explanatory tooltip. Add the closed-episode read-only variant.
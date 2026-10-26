# Matriz de Roles y Permisos

Sistema de pre-registro y gestión de urgencias (Hospital de Ubaté).

---

## 1. Principio de Mínimo Privilegio y Separación de Funciones

El diseño de control de acceso se basa en el principio de menor privilegio (Principle of Least Privilege - PoLP) y en la separación de funciones críticas (Separation of Duties - SoD), garantizando que ningún usuario único tenga control absoluto sobre la operación y el registro de auditoría.

- **Separación Administrador / Auditor**: El rol `administrador` gestiona personal, roles, recursos y usuarios, pero **no tiene permiso para ver ni alterar los registros de auditoría** (`auditoria.ver`). El rol `auditor` es exclusivo de supervisión y no puede crear ni modificar usuarios ni recursos físicos.
- **Acceso Clínico vs. Administrativo**: El personal asistencial (`medico`, `enfermero`, `admisiones`) no cuenta con permisos de gestión de recursos físicos ni de configuración de usuarios; únicamente accede a la visualización de ocupación en tiempo real y a la gestión clínica correspondiente a su rol.
- **Custodia de la Historia Clínica (Res. 1995 de 1999)**: Las atenciones y evoluciones clínicas (`episodios.atender`) solo pueden ser registradas por personal médico y de enfermería calificado.

---

## 2. Matriz Rol - Permiso

| Permiso | Descripción | Administrador | Coordinador | Auditor | Médico | Enfermero | Admisiones | Editor | Autor |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| `usuarios.gestionar` | Crear, editar, suspender y reactivar usuarios y asignar roles | **✓** | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| `auditoria.ver` | Consultar la bitácora de auditoría inmutable del sistema | ✗ | ✗ | **✓** | ✗ | ✗ | ✗ | ✗ | ✗ |
| `correos.ver` | Visualizar la bandeja de correos electrónicos recibidos | **✓** | **✓** | ✗ | ✗ | ✗ | ✗ | **✓** | ✗ |
| `sedes.gestionar` | Crear, editar y deshabilitar sedes hospitalarias y pisos | **✓** | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| `habitaciones.gestionar` | Administrar áreas, habitaciones y capacidades físicas | **✓** | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| `camillas.gestionar` | Habilitar, poner fuera de servicio, mantenimiento y asignar camillas | **✓** | **✓** | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| `personal.gestionar` | Administrar perfiles de personal clínico y estados (descanso/activo) | **✓** | **✓** | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| `turnos.gestionar` | Programar, iniciar y finalizar turnos de personal asistencial | **✓** | **✓** | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| `ocupacion.ver` | Visualizar el censo de camillas por estado y habitaciones en tiempo real | **✓** | **✓** | ✗ | **✓** | **✓** | **✓** | ✗ | ✗ |
| `episodios.ver` | Consultar listado y detalle de historias clínicas de urgencias | **✓** | **✓** | **✓** | **✓** | **✓** | **✓** | ✗ | ✗ |
| `episodios.llegada` | Registrar hora de llegada física en sala de espera | ✗ | **✓** | ✗ | ✗ | **✓** | **✓** | ✗ | ✗ |
| `episodios.triage` | Asignar y clasificar nivel de triage (Resolución 5596 de 2015) | ✗ | **✓** | ✗ | **✓** | **✓** | ✗ | ✗ | ✗ |
| `episodios.ingreso` | Confirmar ingreso formal a atención médica | ✗ | **✓** | ✗ | **✓** | **✓** | ✗ | ✗ | ✗ |
| `episodios.atender` | Registrar evoluciones, diagnósticos y notas inmutables | ✗ | ✗ | ✗ | **✓** | **✓** | ✗ | ✗ | ✗ |
| `episodios.egreso` | Registrar egreso médico formal y cierre de episodio | ✗ | **✓** | ✗ | **✓** | ✗ | ✗ | ✗ | ✗ |
| `episodios.asignar_personal` | Asignar médico responsable o apoyos de enfermería | **✓** | **✓** | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| `episodios.asignar_camilla` | Asignar camilla hospitalaria a paciente ingresado | ✗ | **✓** | ✗ | **✓** | **✓** | ✗ | ✗ | ✗ |
| `episodios.crear_nn` | Registrar ingreso de emergencia para paciente no identificado (NN) | **✓** | **✓** | ✗ | **✓** | **✓** | **✓** | ✗ | ✗ |
| `episodios.conciliar` | Conciliar y vincular identidad verificada a episodio NN | **✓** | **✓** | ✗ | **✓** | ✗ | **✓** | ✗ | ✗ |

---

## 3. Justificación de Asignaciones

1. **Administrador**: Posee control de infraestructura y asignación de personal. No accede a auditoría para evitar auto-borrado ni a atención médica por no ser profesional clínico.
2. **Coordinador de Urgencias**: Supervisa la operación de urgencias, camillas, turnos y flujo de pacientes.
3. **Auditor**: Supervisa cumplimiento normativo de la Ley 1581 de 2012 e inmutabilidad de la historia clínica. Solo lectura.
4. **Médico**: Profesional autorizado para clasificación de triage, ingreso, atenciones diagnósticas/procedimentales y egreso.
5. **Enfermero**: Profesional autorizado para registro de llegada, triage, atenciones de enfermería y asignación de camilla.
6. **Admisiones**: Personal administrativo enfocado en la recepción del paciente y registro de llegada.

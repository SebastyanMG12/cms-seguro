# Matriz de Roles y Permisos

Sistema de pre-registro y gestión de urgencias (Hospital de Ubaté).

---

## 1. Principio de Mínimo Privilegio y Separación de Funciones

El diseño de control de acceso se basa en el principio de menor privilegio (Principle of Least Privilege - PoLP) y en la separación de funciones críticas (Separation of Duties - SoD), garantizando que ningún usuario único tenga control absoluto sobre la operación y el registro de auditoría.

- **Separación Administrador / Auditor**: El rol `administrador` gestiona personal, roles, recursos y usuarios, pero **no tiene permiso para ver ni alterar los registros de auditoría** (`auditoria.ver`). El rol `auditor` es exclusivo de supervisión y no puede crear ni modificar usuarios ni recursos físicos.
- **Acceso Clínico vs. Administrativo**: El personal asistencial (`medico`, `enfermero`, `admisiones`) no cuenta con permisos de gestión de recursos físicos ni de configuración de usuarios; únicamente accede a la visualización de ocupación en tiempo real y a la gestión clínica correspondiente a su rol.

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

---

## 3. Justificación de Asignaciones

1. **Administrador**: Posee control integral de la infraestructura física y de usuarios para el mantenimiento del sistema hospitalario. No tiene acceso a la auditoría para evitar que un administrador oculte o borre sus propias acciones.
2. **Coordinador de Urgencias**: Supervisa la operación diaria de urgencias. Requiere asignar y gestionar camillas, turnos y perfiles de personal para reaccionar a picos de demanda.
3. **Auditor**: Rol técnico de control interno y cumplimiento de la Ley 1581 de 2012. Solo visualiza registros inmutables.
4. **Médico y Enfermero**: Requieren consultar la ocupación de camillas y habitaciones (`ocupacion.ver`) para definir traslados y atención.
5. **Admisiones**: Requiere consultar la disponibilidad de camillas en tiempo real para orientar el ingreso de pacientes al área de triage y valoración.

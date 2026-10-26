<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class HospitalDocsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hospital:docs 
                            {--seguridad : Muestra exclusivamente la matriz de pruebas de seguridad y mitigaciones}
                            {--modulos : Muestra el detalle funcional de cada módulo del sistema}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Muestra la documentación técnica y el informe de seguridad del Hospital de Ubaté en la terminal';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = base_path('INFORME_IMPLEMENTACION_Y_SEGURIDAD.md');

        if (! file_exists($filePath)) {
            $this->error('No se encontró el archivo de documentación INFORME_IMPLEMENTACION_Y_SEGURIDAD.md');

            return Command::FAILURE;
        }

        $content = file_get_contents($filePath);

        $this->newLine();
        $this->info('========================================================================================');
        $this->info('           HOSPITAL DE UBATÉ - SISTEMA SEGURO DE GESTIÓN Y URGENCIAS                   ');
        $this->info('                     INFORME TÉCNICO Y AUDITORÍA DE SEGURIDAD                          ');
        $this->info('========================================================================================');
        $this->newLine();

        if ($this->option('seguridad')) {
            $this->displaySecuritySection($content);

            return Command::SUCCESS;
        }

        if ($this->option('modulos')) {
            $this->displayModulesSection($content);

            return Command::SUCCESS;
        }

        // Mostrar documento completo formateado línea por línea
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (str_starts_with($trimmed, '# ')) {
                $this->newLine();
                $this->warn('>>> '.mb_strtoupper(substr($trimmed, 2)));
                $this->newLine();
            } elseif (str_starts_with($trimmed, '## ')) {
                $this->newLine();
                $this->info('--- '.substr($trimmed, 3).' ---');
            } elseif (str_starts_with($trimmed, '### ')) {
                $this->line('<fg=cyan;options=bold>'.substr($trimmed, 4).'</>');
            } elseif (str_starts_with($trimmed, '* ') || str_starts_with($trimmed, '- ')) {
                $this->line('  • '.substr($trimmed, 2));
            } elseif (str_starts_with($trimmed, '```')) {
                // Bloques de código o diagramas
                continue;
            } elseif (! empty($trimmed)) {
                $this->line($trimmed);
            }
        }

        $this->newLine();
        $this->info('Para consultar vía web: Inicie el servidor y acceda a http://127.0.0.1:8000/documentacion');
        $this->newLine();

        return Command::SUCCESS;
    }

    private function displaySecuritySection(string $content): void
    {
        $this->info('--- MATRIZ DE PRUEBAS DE SEGURIDAD (OWASP / NORMATIVA COLOMBIANA) ---');
        $this->newLine();

        $rows = [
            ['CSRF Protection', 'Peticiones sin token X-CSRF rechazadas con 419 Page Expired.'],
            ['Rate Limiting', 'Mitigación de fuerza bruta en login/contacto con 429 Too Many Requests.'],
            ['IDOR / Autorización', 'Bloqueo 403 Forbidden ante intentos de modificación entre médicos no asignados.'],
            ['Cache-Control No-Store', 'Cabeceras no-store activas para usuarios autenticados en terminales compartidas.'],
            ['Anti-Enumeración', 'Respuestas homogéneas y rutas con restricciones regex rechazando con 404.'],
            ['Cifrado en Reposo', 'Cifrado AES-256 en datos sensibles y búsqueda segura con índice ciego HMAC.'],
            ['Inmutabilidad Médica', 'Prohibición de update/delete en notas clínicas (Res. 1995 de 1999).'],
            ['Trazabilidad Forense', 'Registro auditado inmutable de todo acceso excepcional y descarga de PDF.'],
        ];

        $this->table(['Vulnerabilidad / Prueba', 'Mecanismo de Mitigación Implementado'], $rows);
    }

    private function displayModulesSection(string $content): void
    {
        $this->info('--- MÓDULOS DEL SISTEMA HOSPITALARIO Y URGENCIAS ---');
        $this->newLine();

        $rows = [
            ['Pre-registro Digital', '/preregistro', 'Captura pública previa, código UBTxxxx, trampa honeypot y alarma médica.'],
            ['Triage y Episodios', '/episodios', 'Clasificación Res. 5596 (Triage I a V), tiempos de espera y censo de pacientes.'],
            ['Pacientes NN', '/episodios/registro-nn', 'Admisión vital inmediata NN-AAAAMMDD-XXXX y conciliación forense con no repudio.'],
            ['Historia Clínica & PDF', '/episodios/{id}/pdf', 'Notas inmutables, protocolo Break-Glass y generación PDF con marca de agua.'],
            ['Censo y Habitaciones', '/ocupacion', 'Control de camillas en tiempo real (disponible, ocupada, mantenimiento, limpieza).'],
            ['Personal y Turnos', '/personal', 'Perfiles asistenciales, turnos rotativos/fijos y cálculo de carga activa.'],
            ['Portal Acompañantes', '/acompanante', 'Consulta en tiempo real con PIN de 4/6 dígitos y consentimiento del paciente.'],
            ['Reportes y Métricas', '/reportes', 'Tiempos reales de espera por nivel de triage y porcentaje de cumplimiento.'],
            ['Auditoría Forense', '/auditoria', 'Bitácora inmutable multi-filtro y exportación CSV con cabecera UTF-8 BOM.'],
        ];

        $this->table(['Módulo Asistencial', 'Ruta', 'Funcionalidades Clave'], $rows);
    }
}

<?php

return static function (array &$data): void {
    if (isset($data['cards']['bienestar']) && is_array($data['cards']['bienestar'])) {
        foreach ($data['cards']['bienestar'] as &$card) {
            if (($card['id'] ?? '') === 'b2') {
                $card['link_url'] = 'https://docs.google.com/forms/d/e/1FAIpQLSeTGaCxPfiKneQhh8dwgKkVUtq7E-ec8PriFnEmtKJEcniygA/viewform';
                $card['link_label'] = 'Inscribirme al encuentro';
            }

            if (($card['id'] ?? '') === 'b4') {
                $card['link_url'] = 'https://positivamente.positiva.gov.co/account/loginpaciente?ReturnUrl=%2F';
                $card['link_label'] = 'Ingresar a Positivamente';
            }
        }
        unset($card);
    }

    if (!isset($data['program_pages']['psicologia-orientacion'])) return;

    $data['program_pages']['psicologia-orientacion']['embeds'] = [
        [
            'id' => 'agenda-psicologia',
            'type' => 'calendar',
            'title' => 'Agenda tu cita de Psicología y Orientación Laboral',
            'description' => 'Consulta la disponibilidad y selecciona la fecha y hora que mejor se ajusten a tus necesidades.',
            'embed_url' => 'https://calendar.google.com/calendar/appointments/schedules/AcZssZ014zt86KwMnK4ODY5Z9yV8arZC6ZMFoIAEhz712NQ3niAkpmQKQfSV_5JpXCGL8nSd701kX0LP?gv=true',
            'external_url' => 'https://calendar.google.com/calendar/u/0/appointments/schedules/AcZssZ014zt86KwMnK4ODY5Z9yV8arZC6ZMFoIAEhz712NQ3niAkpmQKQfSV_5JpXCGL8nSd701kX0LP',
            'action_label' => 'Abrir agendador en Google Calendar',
        ],
        [
            'id' => 'formulario-atencion-2026',
            'type' => 'form',
            'title' => 'Formulario de atención para el Programa de Escucha y Orientación Laboral a funcionarios de la Universidad Internacional del Trópico Americano 2026',
            'description' => 'Diligencia el formulario institucional para solicitar atención dentro del programa durante la vigencia 2026.',
            'embed_url' => 'https://docs.google.com/forms/d/e/1FAIpQLSdV428ehzyJPpAmQal4hbvmlYHXca-FxVjChFqFDkcpX_WZaQ/viewform?embedded=true',
            'external_url' => 'https://docs.google.com/forms/d/e/1FAIpQLSdV428ehzyJPpAmQal4hbvmlYHXca-FxVjChFqFDkcpX_WZaQ/viewform',
            'action_label' => 'Abrir formulario en una pestaña nueva',
        ],
    ];
};

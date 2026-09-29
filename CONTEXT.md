# SETI Assets & Spaces

The language SETI (Oficina de Servicios Especiales de Tecnologías de la Información, TEC, Centro Académico de
Alajuela) uses to track its physical assets, the spaces that hold them and the reservations of those spaces.
Users speak Spanish (in _italics_); the English term is the canonical name used in code and in the database.

## Language

### Assets

**Asset** · _Activo_:
A physical item in SETI's custody that is tracked individually and can carry an inventory label (a PC, monitor,
printer, chair, safe…). Cables, paper, extension cords and similar consumables are not assets.
_Avoid_: equipo, bien, ítem, artículo

**Asset tag** · _Placa_:
The institutional TEC inventory number printed on an asset's label; an asset may not have one yet.
_Avoid_: número de activo, ID

**Asset code** · _Código de activo_:
The identifier the system gives every asset (for example A-0014); always present and never reused.
_Avoid_: ID, número de activo

**Asset category** · _Categoría_:
A broad class of assets used to group statistics: Cómputo, Mobiliario, Audiovisual, Redes, Otros.
_Avoid_: tipo, clase

**Asset type** · _Tipo de activo_:
What an asset is (Monitor, Laptop, Silla, Caja fuerte…); every asset type belongs to exactly one category.
_Avoid_: categoría, nombre

**Brand** · _Marca_:
The manufacturer of an asset or component.
_Avoid_: fabricante

**Supplier** · _Proveedor_:
The company an asset was acquired from.
_Avoid_: vendedor, distribuidor

**Warranty** · _Garantía_:
The period during which the supplier covers an asset; it is valid until its end date passes.
_Avoid_: cobertura

**Condition** · _Condición_:
The physical state of an asset (Buen estado, Regular, Dañado…), each belonging to a color group: green, amber or red.
_Avoid_: estado, color

**Lifecycle status** · _Estado del ciclo de vida_:
Where an asset is in its life: in service, in maintenance, lost or decommissioned.
_Avoid_: estado, disponibilidad

**Custodian** · _Responsable del activo_:
The user accountable for an asset's custody and use.
_Avoid_: dueño, owner, propietario

**Component** · _Componente_:
An internal part of an asset (memory, disk, power supply) recorded for detail but never tracked or labeled on its own.
_Avoid_: pieza, sub-activo

### Asset history

**Movement** · _Movimiento de inventario_:
A change in an asset's physical location: an entry into a space, a transfer between spaces, or an exit from SETI's spaces.
_Avoid_: reubicación, cambio de espacio

**Transfer** · _Traslado_:
A movement from one space to another.
_Avoid_: reasignación, movimiento (for entries and exits)

**Asset event** · _Evento del activo_:
A dated fact that changes an asset's condition or lifecycle status: a damage, loss, recovery or decommission.
_Avoid_: incidente (for decommissions), movimiento

**Damage** · _Daño_:
An asset event recording that an asset is physically harmed, with its description and evidence.
_Avoid_: falla, avería

**Loss** · _Pérdida_:
An asset event recording that an asset can no longer be found; it stops counting as available.
_Avoid_: robo (unless confirmed), extravío

**Recovery** · _Recuperación_:
An asset event recording that a lost asset was found and is back in service.
_Avoid_: reactivación

**Decommission** · _Baja_:
The formal, final removal of an asset from the available inventory, with a reason; its history is kept forever.
_Avoid_: eliminar, borrar, descarte

**Deletion** · _Eliminación_:
Removing a record that was entered by mistake; it disappears from lists but its history remains.
_Avoid_: baja

**Maintenance** · _Mantenimiento_:
Work done or planned on an asset; preventive (a periodic check) or corrective (a repair).
_Avoid_: reparación (as a separate concept), servicio

**Repair** · _Reparación_:
Corrective maintenance, usually fixing a recorded damage.
_Avoid_: arreglo

**Scheduled maintenance** · _Mantenimiento programado_:
Maintenance planned for a future date that has not started yet.
_Avoid_: mantenimiento pendiente (which also includes work in progress)

**Pending maintenance** · _Mantenimiento pendiente_:
Maintenance that is scheduled or in progress.
_Avoid_: mantenimiento abierto

### Spaces

**Space** · _Espacio_:
A bounded room SETI administers (a lab, office or meeting room) that holds assets and, if reservable, can be booked.
_Avoid_: local, recinto, ambiente, laboratorio (for spaces in general)

**Space type** · _Tipo de espacio_:
Laboratorio, Oficina or Sala; it decides whether spaces of that type can be reserved.
_Avoid_: categoría de espacio

**Building** · _Edificio_:
The building a space is in, the main part of its location.
_Avoid_: sede, bloque

**Operational status** · _Estado operativo_:
Whether a space can be used: Disponible, En mantenimiento or Inhabilitado; set by its managers.
_Avoid_: estado, ocupado (as a status)

**Occupied** · _Ocupado_:
A space with a reservation happening right now; it is always computed from reservations, never set by hand.
_Avoid_: estado ocupado

**Space manager** · _Encargado del espacio_:
An Administrador assigned to a space who answers for it and manages its assets and reservations; a space may have several.
_Avoid_: responsable del espacio, administrador asignado, dueño del laboratorio

### Reservations

**Reservation** · _Reserva_:
A confirmed claim on one space for one continuous block of time.
_Avoid_: apartado, solicitud, booking

**Reservation series** · _Serie de reservas_:
Reservations created together to repeat the same booking on several dates; each date can be changed or cancelled on its own.
_Avoid_: reserva recurrente (as a single reservation)

**Reservation responsible** · _Responsable de la reserva_:
The user a space is booked for; may differ from the user who made the reservation.
_Avoid_: solicitante, dueño de la reserva

**Cancellation** · _Cancelación_:
Ending a reservation before it takes place; the reservation stays in history. "Eliminar reserva" in the interface means cancelling it.
_Avoid_: eliminar reserva, borrar reserva

**Finished reservation** · _Reserva finalizada_:
A reservation that was not cancelled and whose end time has passed.
_Avoid_: reserva cerrada

**Modified reservation** · _Reserva modificada_:
A reservation that was changed after it was made; a label, not a separate state.
_Avoid_: estado modificada

**Allowed hours** · _Horario permitido_:
The time window of the day and week in which reservations may take place.
_Avoid_: horario de atención

### People & access

**User** · _Usuario_:
A person with an account, identified by their email address.
_Avoid_: nombre de usuario (as a login name), cuenta

**Invitation** · _Invitación_:
The single-use emailed link a new user follows to set their own password.
_Avoid_: contraseña temporal

**Deactivation** · _Desactivación_:
Revoking a user's access while keeping all their history; users are never deleted.
_Avoid_: eliminar usuario, dar de baja a un usuario

**Role** · _Rol_:
A named bundle of permissions; a user holds one or more roles and gets all of their permissions together.
_Avoid_: perfil, tipo de usuario

**Permission** · _Permiso_:
The right to perform one kind of operation, such as registering assets or exporting reports.
_Avoid_: acceso, privilegio

**Superusuario**:
The role that can do everything, including managing users, roles and space managers.
_Avoid_: super admin, root

**Administrador**:
The role for staff who manage the assets, data and reservations of the spaces they are assigned to.
_Avoid_: admin normal, administrador de laboratorio

**Reportes**:
The role that can view dashboards and generate and export reports.
_Avoid_: rol de reportería

**Visualizador**:
The read-only role; it can consult information but never create, change or delete it.
_Avoid_: usuario normal, invitado

**Reservante**:
The role that can reserve spaces and change or cancel its own reservations.
_Avoid_: solicitante

### Reporting & audit

**Report** · _Reporte_:
A filtered, tabular view of system information that can be previewed and exported as PDF or XLSX.
_Avoid_: listado, consulta

**Report generation** · _Generación de reporte_:
One export of a report, kept with its filters, format, author, time and the exported file.
_Avoid_: descarga

**Dashboard** · _Tablero_:
An interactive visual summary of system information, in PowerBI or in the application.
_Avoid_: panel, reporte

**Audit entry** · _Registro de auditoría_:
An unchangeable record of who did what, to which element, when and from where.
_Avoid_: bitácora, log, historial (for the audit trail itself)

**Notification** · _Notificación_:
A message in a user's inbox about something that happened, such as a reservation in a space they manage.
_Avoid_: alerta, aviso, mensaje

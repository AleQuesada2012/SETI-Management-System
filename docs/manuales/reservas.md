# Reservas

En el menú lateral, **Reservas** abre el calendario de reservas de todos los espacios. Desde ahí se reserva un
espacio, se consulta el detalle de cada reserva y se llega al **Historial** (ver `reservas-historial.md`) y a la
**Ocupación** de los espacios (ver `reservas-ocupacion.md`).

Quién puede hacer qué:

- toda cuenta con acceso a Reservas ve el calendario, el detalle de las reservas y la disponibilidad de un espacio;
- el Reservante, el Administrador y el Superusuario pueden reservar;
- quien hizo una reserva o es su responsable puede modificarla o eliminarla; el Administrador puede hacerlo con
  cualquier reserva de los espacios que tiene a cargo, y el Superusuario con todas.

## Calendario

El calendario muestra un mes a la vez, de lunes a domingo. Las flechas junto al nombre del mes pasan al **Mes
anterior** o al **Mes siguiente**. Cada día con reservas tiene un punto de color y, en la computadora, la cantidad de
reservas; la leyenda debajo distingue **Con reservas** de **Libre**. Las reservas eliminadas no aparecen en el
calendario, solo en el historial.

Toque un día para ver sus reservas debajo del calendario: el horario, el espacio, el motivo y la persona para quien se
reservó (**Reservado para …**). El espacio abre la página del espacio y el motivo abre el detalle de la reserva. Si
el día no tiene reservas, la pantalla dice que todos los espacios están libres.

## Reservar un espacio

1. En **Reservas**, toque **Reservar espacio**.
2. En **Espacio**, elija el espacio. Debajo aparecen su ubicación y su capacidad.
3. Elija la **Fecha**, la **Hora de inicio** y la **Hora de fin**. Las horas van en franjas de 15 minutos y la hora
   de fin solo ofrece horas posteriores a la de inicio.
4. Escriba el **Motivo** (por ejemplo, "Tutoría de cálculo") y la **Cantidad de personas**.
5. En **Responsable**, deje su nombre o elija a otra persona; la reserva aparecerá también en el historial de ella.
   **Notas** es opcional.
6. Toque **Reservar**. El sistema muestra **Se reservó …** y abre el detalle de la reserva.

**Cancelar** vuelve al calendario sin reservar.

Reglas que el sistema revisa antes de reservar:

- Solo se ofrecen los espacios que se pueden reservar: laboratorios y salas en estado **Disponible**. Las oficinas y
  los espacios **En mantenimiento** o **Inhabilitado** no aparecen.
- La reserva debe empezar después del momento actual y quedar dentro del horario permitido: de lunes a sábado, de
  07:00 a 22:00. La descripción del formulario recuerda ese horario.
- La cantidad de personas no puede superar la capacidad del espacio.
- El espacio no puede estar reservado en ese horario. Si lo está, el sistema dice **El espacio ya está reservado en
  ese horario** y sugiere revisar las reservas del espacio o el calendario. Aunque dos personas reserven el mismo
  horario al mismo tiempo, solo una de las reservas se guarda.

Cada error aparece junto al campo que hay que corregir y los datos escritos se conservan.

Al reservar, el Superusuario y los encargados del espacio reciben una notificación en la campana (ver
"Notificaciones de reservas" en `README.md`).

## Consultar la disponibilidad de un espacio

En **Espacios**, abra un espacio y baje hasta **Disponibilidad**:

1. Elija la **Fecha**, la **Hora de inicio** y la **Hora de fin**.
2. Toque **Consultar disponibilidad**.

Si el horario está libre, el sistema responde **Disponible** y, a quien puede reservar, le ofrece **Reservar este
horario**, que abre el formulario de reserva con el espacio, la fecha y las horas ya elegidos. Si está ocupado,
responde **No disponible** y lista las reservas que ocupan el espacio en ese horario. En un espacio que no se puede
reservar (una oficina o un espacio en mantenimiento), la sección lo indica.

## Reservas de un espacio

En la página de un espacio, **Reservas del espacio** lista las reservas del mes actual con su fecha, horario,
motivo, responsable, cantidad de personas y estado. Cambie **Desde** y **Hasta** y toque **Consultar** para ver otro
período; si no hay reservas, la sección dice que el espacio no tiene reservas en ese período.

## Detalle de una reserva

El detalle muestra el espacio, el **Estado actual**, la fecha, el horario, el motivo, la cantidad de personas, el
responsable, quién la reservó y las notas. El estado es **Próxima**, **En curso**, **Finalizada** o **Cancelada**, con
la etiqueta **Modificada** cuando la reserva cambió después de hacerse; en ese caso se muestra también el motivo de la
última modificación. Una reserva cancelada indica cuándo y quién la eliminó, y el motivo si se anotó.

Mientras la reserva no haya comenzado, quien tiene permiso ve dos botones:

- **Modificar** abre el formulario con los datos actuales; se puede cambiar el espacio, la fecha, el horario o los
  datos y anotar un **Motivo de la modificación (opcional)**. Se aplican las mismas reglas que al reservar. **Guardar
  cambios** confirma y **Volver** regresa al detalle sin cambiar nada.
- **Eliminar** pide confirmación y permite anotar un motivo opcional. **Eliminar reserva** cancela la reserva: el
  horario queda libre y la reserva sigue en el historial como **Cancelada**. **Volver** cierra el diálogo sin
  eliminar.

Modificar y eliminar también notifican al Superusuario y a los encargados del espacio. Las reservas en curso o
finalizadas ya no se pueden modificar ni eliminar. Más detalles en "Modificar una reserva" y "Eliminar una reserva"
de `README.md`.

## Lista de espacios

En el menú lateral, **Espacios** lista los espacios con su ubicación, capacidad, tipo y estado; la etiqueta **Ocupado
ahora** marca los que tienen una reserva en curso. Marque una o varias opciones de **Tipo de espacio**, **Estado** y
**Edificio** para filtrar: **Filtros activos** resume la selección y **Limpiar filtros** vuelve a la lista completa.
Las columnas **Creado** y **Modificado** ordenan la lista por fecha. Al abrir un espacio se ven sus datos, sus
encargados, sus **Activos en el espacio**, sus reservas y su disponibilidad.

## Probarlo

En el ambiente local, entre con `reservante@seti.test` (contraseña `password`), reserve **LAB-110** y revise la
campana con `superusuario@seti.test` o `admin@seti.test`, que es encargado de LAB-110.

# Manuales de usuario

Guías en español para quienes usan el sistema en SETI. Cada módulo agrega aquí su manual a partir del Sprint 2
(ver la Definición de Terminado en `CONTRIBUTING.md`): qué hace cada pantalla, quién puede usarla y los pasos de
cada acción, con capturas tomadas en el teléfono y en la computadora.

| Manual | Módulo | Estado |
|---|---|---|
| `inicio-de-sesion.md` | Seguridad | Pendiente |
| `activos.md` | Activos | Pendiente |
| `espacios.md` | Espacios | Pendiente |
| `reservas.md` | Reservas | Pendiente |
| `reportes.md` | Reportes | Pendiente |
| `usuarios-y-roles.md` | Usuarios y roles | Pendiente |

## Menú de reportes (RF_60)

La cuenta de demostración `reportes@seti.test` (contraseña `password`) puede abrir **Reportes** y cambiar entre
Activos, Espacios, Auditoría y Usuarios. Cada categoría ofrece los reportes implementados que puede consultar.
Cuando no hay ninguno disponible, aparece un mensaje informativo. Los reportes planificados para sprints futuros
aparecen como **Próximamente**; los que la cuenta no puede consultar se ocultan.

![Menú en computadora](reportes-menu-escritorio.jpg)
![Menú a 360 px](reportes-menu-movil.jpg)

## Movimientos de activos (RF_06, RF_25)

Un **Administrador** puede registrar movimientos de los activos que están (o estuvieron por última vez) en los
espacios de los que es encargado; el **Superusuario**, de cualquier activo. Pruébalo con `admin@seti.test`
(contraseña `password`).

1. Abre un activo desde **Inventario de activos** y pulsa **Registrar movimiento**.
2. Elige el tipo: **Traslado a otro espacio**, **Salida de los espacios de SETI** (reparación externa, préstamo,
   desecho) o, si el activo está fuera, **Ingreso a un espacio**. Solo aparecen los tipos que el estado del activo
   permite: un activo perdido no se mueve hasta registrar su recuperación y uno dado de baja solo puede salir.
3. Elige el espacio destino (no aplica a una salida). La fecha y hora son opcionales: vacías, el movimiento queda con
   la hora actual; también puedes registrar uno anterior, pero no anterior al último movimiento del activo.
4. Escribe el motivo (obligatorio en una salida) y pulsa **Registrar movimiento**. Un aviso confirma el espacio al que
   se trasladó el activo y la ficha muestra su nueva ubicación.

La ficha del activo lista todos sus movimientos, del más reciente al más antiguo, con la fecha, el tipo, el origen,
el destino, quién lo registró y el motivo. Un Administrador que abre un activo de un espacio que no tiene asignado
ve un aviso de que no puede trasladarlo; las cuentas sin el permiso no ven el botón.

## Eliminar un activo (RF_02)

Eliminar sirve solo para un activo **registrado por error**. Si el activo existe y deja de usarse, no se elimina: se
da de baja. Un **Administrador** puede eliminar los activos que están (o estuvieron por última vez) en los espacios de
los que es encargado; el **Superusuario**, cualquiera. Pruébalo con `admin@seti.test` (contraseña `password`).

1. Abre el activo desde **Inventario de activos** y pulsa **Eliminar**.
2. Lee la confirmación y pulsa **Eliminar activo**. Con **Cancelar** el activo queda tal como estaba.
3. Un aviso confirma la eliminación y vuelves al inventario, donde el activo ya no aparece. Tampoco aparece en los
   reportes, pero su historial (movimientos y auditoría) se conserva y su placa queda libre para otro activo.

Si otra persona eliminó el activo mientras tenías abierta su ficha, un aviso indica que ya no existe. Las cuentas sin el
permiso, o un Administrador ante un activo de un espacio que no tiene asignado, no ven el botón.

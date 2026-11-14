import { fileURLToPath } from 'node:url';

/*
 * The demo accounts of DemoSeeder (password "password"). They exist only in the local environment, where two-factor
 * authentication is not required (config/auth.php, TWO_FACTOR_REQUIRED).
 */
export const USERS = {
    superusuario: { email: 'superusuario@seti.test', name: 'Sofía Vargas' },
    // Space manager of LAB-110, LAB-312 and SAL-01.
    administrador: { email: 'admin@seti.test', name: 'Mario Solís' },
    // Space manager of LAB-316, LAB-322 and LAIMI.
    administrador2: { email: 'admin2@seti.test', name: 'Lucía Campos' },
    reportes: { email: 'reportes@seti.test', name: 'Andrés Mora' },
    visualizador: { email: 'visualizador@seti.test', name: 'Paula Jiménez' },
    reservante: { email: 'reservante@seti.test', name: 'Esteban Castro' },
    profesora: { email: 'profesora@seti.test', name: 'Daniela Quirós' },
} as const;

export type Role = keyof typeof USERS;

export const PASSWORD = 'password';

/** The saved session of a role, written by auth.setup.ts and loaded with test.use({ storageState }). */
export function storageState(role: Role): string {
    return fileURLToPath(new URL(`../.auth/${role}.json`, import.meta.url));
}

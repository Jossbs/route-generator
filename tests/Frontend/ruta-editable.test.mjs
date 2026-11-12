import assert from 'node:assert/strict';
import test from 'node:test';
import {
    agregarCurso,
    alternarCurso,
    reemplazarCurso,
    moverCurso,
    moverBloque,
} from '../../resources/js/lib/ruta-editable.ts';

const curso = (id, especialidad_id = id) => ({
    id,
    nombre: `Curso ${id}`,
    horas: 10,
    especialidad_id,
    especialidad: `Especialidad ${especialidad_id}`,
});
const bloque = (ids, especialidad_id, principal = false) => ({
    especialidad_id,
    especialidad: `Especialidad ${especialidad_id}`,
    mes: null,
    principal,
    cursos_en_la_especialidad: 4,
    justificacion: null,
    cursos: ids.map((id) => ({
        id,
        nombre: `Curso ${id}`,
        horas: 10,
        orden: id,
        fijo: principal,
    })),
});
const inicial = () => ({
    bloques: [bloque([1, 2], 10, true), bloque([3, 4], 20), bloque([5], 30)],
    fuera: [],
});
const ids = (state) =>
    state.bloques
        .flatMap((b) => b.cursos.map((c) => c.id))
        .filter((id) => !state.fuera.includes(id));

await test('agrega un cuarto adicional, incluso de la especialidad principal, sin duplicar cursos', () => {
    const state = agregarCurso(inicial(), curso(6, 10));
    assert.deepEqual(ids(state), [1, 2, 3, 4, 5, 6]);
    assert.equal(state.bloques.at(-1).principal, false);
    assert.deepEqual(ids(agregarCurso(state, curso(6, 10))), ids(state));
});

await test('reemplaza entre especialidades y conserva exactamente la posición', () => {
    const state = reemplazarCurso(inicial(), 4, curso(6, 40));
    assert.deepEqual(ids(state), [1, 2, 3, 6, 5]);
    assert.equal(
        state.bloques.find((b) => b.cursos.some((c) => c.id === 6))
            .especialidad_id,
        40,
    );
    assert.deepEqual(ids(inicial()), [1, 2, 3, 4, 5]);
});

await test('permite intercalar especialidades sin reagrupar ni cambiar el orden del asesor', () => {
    const state = moverCurso(inicial(), 5, -1);
    assert.deepEqual(ids(state), [1, 2, 3, 5, 4]);
    assert.deepEqual(
        state.bloques.map((b) => b.especialidad_id),
        [10, 20, 30, 20],
    );
    assert.deepEqual(ids(moverCurso(state, 3, 1)), [1, 2, 5, 3, 4]);
});

await test('saltar un curso quitado mantiene el orden de los adicionales activos', () => {
    const state = moverCurso(alternarCurso(inicial(), 4), 5, -1);
    assert.deepEqual(ids(state), [1, 2, 5, 3]);
    assert.deepEqual(ids(agregarCurso(state, curso(4, 20))), [1, 2, 5, 4, 3]);
});

await test('un curso quitado puede reemplazar otro sin duplicarse en el borrador', () => {
    const state = reemplazarCurso(alternarCurso(inicial(), 3), 5, curso(3, 20));
    assert.deepEqual(ids(state), [1, 2, 4, 3]);
    assert.equal(
        state.bloques.flatMap((b) => b.cursos).filter((c) => c.id === 3).length,
        1,
    );
    assert.deepEqual(
        ids(reemplazarCurso(inicial(), 5, curso(3, 20))),
        [1, 2, 3, 4, 5],
    );
});

await test('conserva la campaña al quitar, reemplazar u ordenar adicionales', () => {
    const state = inicial();
    assert.deepEqual(alternarCurso(state, 1), state);
    assert.deepEqual(reemplazarCurso(state, 2, curso(6)), state);
    assert.deepEqual(moverCurso(state, 1, 1), state);
    assert.deepEqual(moverCurso(state, 3, -1), state);
    assert.deepEqual(moverBloque(state, 1, -1), state);
});

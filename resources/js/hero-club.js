import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/examples/jsm/geometries/RoundedBoxGeometry.js';

/**
 * The homepage hero in 3D, across the full width of the screen: a cross-section
 * of a club. One after another, members walk in from the street, scan the QR
 * code in the app at the scanner on the front desk while the staff member says
 * hello, and walk on to their activity: the dance studio, the gym, the yoga
 * studio, the padel court and the golf room, where the others are already
 * training. The building is wider than the screen, so the camera follows each
 * member to their room.
 *
 * Mounted on [data-hero-club]. The SVG inside stays as the still fallback for
 * visitors who prefer reduced motion or have no WebGL.
 */

/**
 * Seconds the camera stays with a member in their room before the next member
 * walks in. Members arrive one after another: each starts once the previous
 * one has reached their room, so the whole loop takes about a minute.
 */
const DWELL_SECONDS = 2.5;

const WALK_SPEED = 2.8;

/**
 * Left and right end of the building; the camera keeps them in view on wide
 * screens and pans between them on narrow ones.
 */
const SCENE_LEFT = -17;
const SCENE_RIGHT = 29.5;

/**
 * The most of the building the camera shows at once, in scene units: on wide
 * screens it pans along the rest, so the people keep a readable size.
 */
const MAX_VISIBLE_WIDTH = 35.5;

/**
 * Height of the building on screen, in scene units seen from the camera, and
 * the space kept free below it.
 */
const BUILDING_HEIGHT = 5.5;
const BOTTOM_MARGIN = 16;

const COLORS = {
    floor: 0xe0e7ff,
    wall: 0x3b82f6,
    sideWall: 0x6366f1,
    desk: 0xf8fafc,
    brand: 0x2563eb,
    staffShirt: 0x1d4ed8,
    hoodie: 0xf97316,
    pants: 0x1e293b,
    shoe: 0xffffff,
    skinLight: 0xf1c7a5,
    skinDark: 0xb87a55,
    hairBrown: 0x7c2d12,
    hairBlack: 0x111827,
    hairBlond: 0xeab308,
    green: 0x22c55e,
    studioFloor: 0xf6d7b0,
    yogaFloor: 0xede9fe,
    turf: 0x4ade80,
    puttingGreen: 0x16a34a,
    court: 0x2563eb,
    glass: 0xbfdbfe,
};

const clamp01 = (value) => Math.min(1, Math.max(0, value));
const smooth = (value) => value * value * (3 - 2 * value);

/**
 * Progress (0..1, eased) of the loop between two moments, in fractions of the loop.
 */
const phase = (time, from, to) => smooth(clamp01((time - from) / (to - from)));

function material(color, options = {}) {
    return new THREE.MeshStandardMaterial({ color, roughness: 0.7, metalness: 0, ...options });
}

function mesh(geometry, meshMaterial, { x = 0, y = 0, z = 0, shadow = true } = {}) {
    const result = new THREE.Mesh(geometry, meshMaterial);

    result.position.set(x, y, z);
    result.castShadow = shadow;
    result.receiveShadow = true;

    return result;
}

const box = (width, height, depth, radius = 0.06) => new RoundedBoxGeometry(width, height, depth, 3, radius);

/**
 * A text label drawn on a canvas, shown as a sprite that always faces the camera.
 */
function labelSprite(lines, { width = 512, height = 160, check = false } = {}) {
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');

    canvas.width = width;
    canvas.height = height;

    context.fillStyle = '#ffffff';
    context.beginPath();
    context.roundRect(8, 8, width - 16, height - 16, 44);
    context.fill();

    let textX = width / 2;
    context.textAlign = 'center';

    if (check) {
        context.fillStyle = '#22c55e';
        context.beginPath();
        context.arc(86, height / 2, 42, 0, Math.PI * 2);
        context.fill();
        context.strokeStyle = '#ffffff';
        context.lineWidth = 12;
        context.lineCap = 'round';
        context.lineJoin = 'round';
        context.beginPath();
        context.moveTo(66, height / 2);
        context.lineTo(80, height / 2 + 14);
        context.lineTo(106, height / 2 - 14);
        context.stroke();
        textX = 150;
        context.textAlign = 'left';
    }

    const [title, subtitle] = lines;

    context.fillStyle = '#0f172a';
    context.font = `600 ${subtitle ? 46 : 54}px Poppins, sans-serif`;
    context.textBaseline = 'middle';
    context.fillText(title, textX, subtitle ? height / 2 - 18 : height / 2);

    if (subtitle) {
        context.fillStyle = '#64748b';
        context.font = '400 32px Poppins, sans-serif';
        context.fillText(subtitle, textX, height / 2 + 30);
    }

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.anisotropy = 4;

    const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ map: texture, transparent: true, depthTest: false }));
    sprite.scale.set(width / 300, height / 300, 1);
    sprite.renderOrder = 10;

    return sprite;
}

/**
 * The club sign on the back wall.
 */
function signTexture(text) {
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');

    canvas.width = 1024;
    canvas.height = 160;
    context.fillStyle = 'rgba(255, 255, 255, 0.16)';
    context.beginPath();
    context.roundRect(0, 0, 1024, 160, 48);
    context.fill();
    context.fillStyle = '#ffffff';
    context.beginPath();
    context.arc(90, 80, 40, 0, Math.PI * 2);
    context.fill();
    context.strokeStyle = '#2563eb';
    context.lineWidth = 12;
    context.lineCap = 'round';
    context.lineJoin = 'round';
    context.beginPath();
    context.moveTo(70, 80);
    context.lineTo(84, 94);
    context.lineTo(110, 66);
    context.stroke();
    context.fillStyle = '#ffffff';
    context.font = '600 64px Poppins, sans-serif';
    context.textBaseline = 'middle';
    context.fillText(text, 160, 84);

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;

    return texture;
}

/**
 * A QR-like pattern for the phone screen.
 */
function qrTexture() {
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');
    const cells = 9;
    const size = 18;

    canvas.width = canvas.height = cells * size + 24;
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.fillStyle = '#1e3a8a';

    let seed = 7;
    const random = () => ((seed = (seed * 9301 + 49297) % 233280) / 233280);
    const finder = (column, row) => {
        context.fillRect(12 + column * size, 12 + row * size, size * 3, size * 3);
        context.clearRect(12 + column * size + 8, 12 + row * size + 8, size * 3 - 16, size * 3 - 16);
        context.fillStyle = '#ffffff';
        context.fillRect(12 + column * size + 8, 12 + row * size + 8, size * 3 - 16, size * 3 - 16);
        context.fillStyle = '#1e3a8a';
        context.fillRect(12 + column * size + 18, 12 + row * size + 18, size * 3 - 36, size * 3 - 36);
    };

    for (let column = 0; column < cells; column++) {
        for (let row = 0; row < cells; row++) {
            const inFinder = (column < 3 && row < 3) || (column > 5 && row < 3) || (column < 3 && row > 5);

            if (! inFinder && random() < 0.5) {
                context.fillRect(12 + column * size, 12 + row * size, size - 2, size - 2);
            }
        }
    }

    finder(0, 0);
    finder(6, 0);
    finder(0, 6);

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;

    return texture;
}

/**
 * A rounded, toy-like character. Faces +z; arms and legs hang from pivots so
 * they can swing. Returns the root plus the pivots to animate.
 */
function character({ shirt, skin, hair, pants = COLORS.pants, hood = false }) {
    const root = new THREE.Group();
    const body = new THREE.Group();
    root.add(body);

    const shirtMaterial = material(shirt);
    const skinMaterial = material(skin);
    const hairMaterial = material(hair, { roughness: 0.9 });
    const pantsMaterial = material(pants);
    const shoeMaterial = material(COLORS.shoe);

    const legs = [-0.11, 0.11].map((x) => {
        const pivot = new THREE.Group();
        pivot.position.set(x, 0.86, 0);
        pivot.add(mesh(new THREE.CapsuleGeometry(0.085, 0.62, 4, 12), pantsMaterial, { y: -0.4 }));
        pivot.add(mesh(box(0.17, 0.1, 0.3, 0.05), shoeMaterial, { y: -0.82, z: 0.05 }));
        body.add(pivot);

        return pivot;
    });

    body.add(mesh(box(0.5, 0.66, 0.3, 0.13), shirtMaterial, { y: 1.18 }));

    if (hood) {
        body.add(mesh(new THREE.TorusGeometry(0.15, 0.05, 8, 20), shirtMaterial, { y: 1.5, z: -0.04 }));
    } else {
        const collar = mesh(new THREE.ConeGeometry(0.08, 0.1, 3), material(0xffffff), { y: 1.47, z: 0.13 });
        collar.rotation.x = Math.PI;
        body.add(collar);
    }

    body.add(mesh(new THREE.CylinderGeometry(0.065, 0.07, 0.12, 12), skinMaterial, { y: 1.55 }));

    const head = new THREE.Group();
    head.position.y = 1.76;
    head.add(mesh(new THREE.SphereGeometry(0.2, 24, 18), skinMaterial));

    const hairCap = mesh(new THREE.SphereGeometry(0.212, 24, 18, 0, Math.PI * 2, 0, Math.PI * 0.5), hairMaterial, { y: 0.02, z: -0.015 });
    hairCap.rotation.x = -0.25;
    head.add(hairCap);

    const eyeMaterial = material(0x1e293b);
    [-0.07, 0.07].forEach((x) => head.add(mesh(new THREE.SphereGeometry(0.022, 10, 8), eyeMaterial, { x, y: 0.0, z: 0.185, shadow: false })));

    const smile = mesh(new THREE.TorusGeometry(0.05, 0.012, 6, 16, Math.PI), material(0x9a3412), { y: -0.07, z: 0.18, shadow: false });
    smile.rotation.z = Math.PI;
    head.add(smile);
    body.add(head);

    const arms = [-0.31, 0.31].map((x) => {
        const pivot = new THREE.Group();
        pivot.position.set(x, 1.44, 0);
        pivot.add(mesh(new THREE.CapsuleGeometry(0.07, 0.5, 4, 12), shirtMaterial, { y: -0.3 }));
        pivot.add(mesh(new THREE.SphereGeometry(0.075, 14, 10), skinMaterial, { y: -0.62 }));
        body.add(pivot);

        return pivot;
    });

    const materials = [shirtMaterial, skinMaterial, hairMaterial, pantsMaterial, shoeMaterial, eyeMaterial];

    return { root, body, legs, arms, materials };
}

/**
 * A padel racket for a character's hand.
 */
function racket() {
    const group = new THREE.Group();
    group.add(mesh(new THREE.CylinderGeometry(0.025, 0.025, 0.22, 8), material(0x0f172a), { y: -0.1 }));
    const blade = mesh(new THREE.CylinderGeometry(0.17, 0.17, 0.035, 20), material(0xf97316), { y: -0.35 });
    blade.rotation.x = Math.PI / 2;
    blade.scale.set(1, 1, 1.25);
    group.add(blade);
    group.position.y = -0.62;

    return group;
}

/**
 * A golf club (or putter) for a character's hand, long enough to reach the floor.
 */
function golfClub({ length = 0.9 } = {}) {
    const group = new THREE.Group();
    group.add(mesh(new THREE.CylinderGeometry(0.03, 0.03, 0.18, 8), material(0x0f172a), { y: -0.06 }));
    group.add(mesh(new THREE.CylinderGeometry(0.014, 0.014, length, 6), material(0xcbd5e1, { metalness: 0.6, roughness: 0.3 }), { y: -length / 2 }));
    group.add(mesh(box(0.16, 0.06, 0.07, 0.02), material(0x94a3b8, { metalness: 0.6, roughness: 0.3 }), { x: 0.05, y: -length }));
    group.position.y = -0.62;

    return group;
}

/**
 * A flat sign on the back wall.
 */
function wallSign(scene, text, { x, y, width }) {
    const sign = new THREE.Mesh(new THREE.PlaneGeometry(width, width * 0.156), new THREE.MeshBasicMaterial({ map: signTexture(text), transparent: true }));
    sign.position.set(x, y, -2.29);
    scene.add(sign);
}

function buildScene(scene, texts) {
    const actors = {};

    // Street, floor slab and back wall: a long cutaway of the building, open at the top.
    scene.add(mesh(box(4.4, 0.16, 5.2, 0.05), material(0xcbd5e1), { x: -15.2, y: -0.08, shadow: false }));
    scene.add(mesh(box(42.6, 0.4, 5.2, 0.12), material(COLORS.floor), { x: 8.25, y: -0.2, shadow: false }));
    scene.add(mesh(box(42.6, 3.6, 0.3, 0.08), material(COLORS.wall), { x: 8.25, y: 1.8, z: -2.45, shadow: false }));
    scene.add(mesh(box(42.8, 0.16, 0.5, 0.06), material(0xf8fafc), { x: 8.25, y: 3.66, z: -2.35, shadow: false }));
    scene.add(mesh(box(0.3, 3.6, 5.2, 0.08), material(COLORS.sideWall), { x: -13.05, y: 1.8, shadow: false }).translateZ(0));

    // Entrance: an opening in the street-side wall.
    scene.add(mesh(box(0.34, 2.4, 1.5, 0.04), material(0x93c5fd, { emissive: 0x60a5fa, emissiveIntensity: 0.25 }), { x: -13.05, y: 1.2, z: 1.0, shadow: false }));

    // Street tree and bike.
    scene.add(mesh(new THREE.CylinderGeometry(0.08, 0.1, 1.6, 8), material(0x92400e), { x: -16, y: 0.8, z: -1.4 }));
    scene.add(mesh(new THREE.SphereGeometry(0.7, 16, 12), material(0x34d399), { x: -16, y: 2.0, z: -1.4 }));

    // Room dividers: half-depth walls, so the walking lane at the front stays open.
    [-7.2, 2.4, 8.2, 13.6, 22.0].forEach((x) => scene.add(mesh(box(0.2, 3.6, 2.6, 0.05), material(0xdbeafe), { x, y: 1.8, z: -1.1, shadow: false })));

    // Lobby: sign, front desk, staff member and the check-in gate.
    wallSign(scene, texts.club, { x: -10.2, y: 2.95, width: 4.6 });

    const desk = new THREE.Group();
    desk.position.set(-10.6, 0, -0.9);
    desk.add(mesh(box(2.6, 1.0, 0.8, 0.08), material(COLORS.desk), { y: 0.5 }));
    desk.add(mesh(box(2.62, 0.14, 0.82, 0.04), material(COLORS.brand), { y: 0.36 }));
    desk.add(mesh(box(2.9, 0.1, 1.0, 0.05), material(0xffffff), { y: 1.05 }));
    desk.add(mesh(box(0.6, 0.4, 0.06, 0.03), material(0xe2e8f0), { x: -0.5, y: 1.33, z: -0.1 }));
    scene.add(desk);

    actors.staff = character({ shirt: COLORS.staffShirt, skin: COLORS.skinLight, hair: COLORS.hairBrown });
    actors.staff.root.position.set(-10.9, 0, -1.75);
    scene.add(actors.staff.root);

    // QR scanner on the front corner of the desk, facing the visitors.
    const scanner = new THREE.Group();
    scanner.position.set(-9.55, 1.1, -0.6);
    scanner.add(mesh(new THREE.CylinderGeometry(0.03, 0.05, 0.24, 10), material(0x475569), { y: 0.12 }));
    const scannerHead = new THREE.Group();
    scannerHead.position.y = 0.3;
    scannerHead.rotation.x = -0.45;
    scannerHead.add(mesh(box(0.3, 0.26, 0.08, 0.03), material(0x0f172a)));
    const scannerMaterial = new THREE.MeshStandardMaterial({ color: 0x1e3a8a, emissive: 0x1e3a8a, emissiveIntensity: 0.5 });
    const screen = new THREE.Mesh(new THREE.PlaneGeometry(0.22, 0.18), scannerMaterial);
    screen.position.z = 0.045;
    scannerHead.add(screen);
    scanner.add(scannerHead);
    const beamMaterial = new THREE.MeshBasicMaterial({ color: 0xf87171, transparent: true, opacity: 0, depthWrite: false, side: THREE.DoubleSide });
    const beam = new THREE.Mesh(new THREE.ConeGeometry(0.14, 0.45, 16, 1, true), beamMaterial);
    beam.rotation.x = -Math.PI / 2;
    beam.position.set(0, 0.32, 0.27);
    scanner.add(beam);
    scene.add(scanner);
    Object.assign(actors, { scannerMaterial, beamMaterial });

    // Dance studio: a wooden floor, a mirror with a barre, a teacher and dancers.
    scene.add(mesh(box(9.4, 0.04, 4.4, 0.02), material(COLORS.studioFloor), { x: -2.4, y: 0.02, z: -0.2, shadow: false }));
    scene.add(mesh(box(7.5, 2.1, 0.05, 0.02), material(0xe0f2fe, { roughness: 0.15, metalness: 0.3 }), { x: -2.4, y: 1.45, z: -2.27, shadow: false }));
    const barreMaterial = material(0xd6a26a);
    const barre = mesh(new THREE.CylinderGeometry(0.035, 0.035, 7.2, 10), barreMaterial, { x: -2.4, y: 1.0, z: -2.08 });
    barre.rotation.z = Math.PI / 2;
    scene.add(barre);
    [-5.8, -2.4, 1.0].forEach((x) => scene.add(mesh(box(0.05, 0.05, 0.22, 0.01), barreMaterial, { x, y: 1.0, z: -2.18 })));
    wallSign(scene, texts.studio, { x: -2.4, y: 3.05, width: 3.6 });

    actors.instructor = character({ shirt: 0xec4899, skin: COLORS.skinLight, hair: COLORS.hairBlond, pants: 0x0f172a });
    actors.instructor.root.position.set(-2.4, 0.04, -1.6);
    scene.add(actors.instructor.root);

    const classmates = [
        [-5.1, -0.35, 0x10b981, COLORS.skinDark, COLORS.hairBlack],
        [-3.6, -0.15, 0x8b5cf6, COLORS.skinLight, COLORS.hairBrown],
        [0.3, -0.35, 0x0ea5e9, 0xd6a07a, COLORS.hairBlack],
    ];
    actors.classmates = classmates.map(([x, z, shirt, skin, hair]) => {
        const classmate = character({ shirt, skin, hair });
        classmate.root.position.set(x, 0.04, z);
        classmate.root.rotation.y = Math.PI + (x < -2.4 ? 0.35 : -0.35);
        scene.add(classmate.root);

        return classmate;
    });

    // Gym: a treadmill runner and someone lifting dumbbells.
    [-1.2, 0.5].forEach((z) => {
        const treadmill = new THREE.Group();
        treadmill.position.set(4.0, 0, z);
        treadmill.add(mesh(box(1.8, 0.2, 0.8, 0.06), material(0x334155), { y: 0.1 }));
        treadmill.add(mesh(box(0.1, 1.2, 0.1, 0.03), material(0x475569), { x: -0.85, y: 0.7, z: -0.3 }));
        treadmill.add(mesh(box(0.1, 1.2, 0.1, 0.03), material(0x475569), { x: -0.85, y: 0.7, z: 0.3 }));
        treadmill.add(mesh(box(0.3, 0.2, 0.8, 0.04), material(0x1e293b), { x: -0.85, y: 1.3 }));
        scene.add(treadmill);
    });

    actors.runner = character({ shirt: 0xef4444, skin: 0xd6a07a, hair: COLORS.hairBrown });
    actors.runner.root.position.set(4.1, 0.2, -1.2);
    actors.runner.root.rotation.y = -Math.PI / 2;
    scene.add(actors.runner.root);

    const rackMaterial = material(0xe2e8f0);
    scene.add(mesh(box(2.0, 0.08, 0.5, 0.03), rackMaterial, { x: 6.4, y: 0.6, z: -1.95 }));
    [5.6, 6.0, 6.4, 6.8, 7.2].forEach((x) => {
        const weight = mesh(new THREE.CylinderGeometry(0.1, 0.1, 0.32, 12), material(0x334155), { x, y: 0.73, z: -1.95 });
        weight.rotation.x = Math.PI / 2;
        scene.add(weight);
    });

    actors.lifter = character({ shirt: 0x14b8a6, skin: COLORS.skinDark, hair: COLORS.hairBlack });
    actors.lifter.root.position.set(6.4, 0, -0.8);
    actors.lifter.root.rotation.y = -0.4;
    actors.lifter.arms.forEach((arm) => {
        const dumbbell = mesh(new THREE.CylinderGeometry(0.04, 0.04, 0.3, 8), material(0x334155), { y: -0.66 });
        dumbbell.rotation.z = Math.PI / 2;
        arm.add(dumbbell);
    });
    scene.add(actors.lifter.root);

    // Yoga studio: a calm floor, mats, a teacher and students flowing between poses.
    scene.add(mesh(box(5.2, 0.04, 4.4, 0.02), material(COLORS.yogaFloor), { x: 10.9, y: 0.02, z: -0.2, shadow: false }));
    wallSign(scene, texts.yoga, { x: 10.9, y: 3.05, width: 3.0 });
    scene.add(mesh(box(1.5, 0.03, 0.7, 0.01), material(0x14b8a6), { x: 10.9, y: 0.05, z: -1.7, shadow: false }));
    [[9.5, -0.3, 0xf472b6], [12.3, -0.3, 0xa78bfa], [10.9, 0.45, 0x60a5fa]].forEach(([x, z, color]) => {
        scene.add(mesh(box(0.7, 0.03, 1.5, 0.01), material(color), { x, y: 0.05, z, shadow: false }));
    });

    actors.yogaTeacher = character({ shirt: 0x14b8a6, skin: 0xd6a07a, hair: COLORS.hairBlack, pants: 0x334155 });
    actors.yogaTeacher.root.position.set(10.9, 0.04, -1.7);
    scene.add(actors.yogaTeacher.root);

    actors.yogis = [[9.5, 0xf472b6, COLORS.skinLight, COLORS.hairBlond], [12.3, 0xa78bfa, COLORS.skinDark, COLORS.hairBrown]].map(([x, shirt, skin, hair]) => {
        const yogi = character({ shirt, skin, hair, pants: 0x475569 });
        yogi.root.position.set(x, 0.04, -0.3);
        yogi.root.rotation.y = Math.PI + (x < 10.9 ? 0.4 : -0.4);
        scene.add(yogi.root);

        return yogi;
    });

    // Padel court: blue floor, glass ends, a net and two players rallying. Built
    // in its own group, so its coordinates stay local to the court.
    const court = new THREE.Group();
    court.position.x = PADEL_SHIFT;
    scene.add(court);
    court.add(mesh(box(7.4, 0.05, 4.2, 0.02), material(COLORS.court), { x: 12.4, y: 0.03, z: -0.2, shadow: false }));
    const lineMaterial = material(0xffffff);
    court.add(mesh(box(7.0, 0.01, 0.05, 0.005), lineMaterial, { x: 12.4, y: 0.06, z: -0.2, shadow: false }));
    [9.6, 15.2].forEach((x) => court.add(mesh(box(0.05, 0.01, 4.0, 0.005), lineMaterial, { x, y: 0.06, z: -0.2, shadow: false })));
    const glassMaterial = new THREE.MeshStandardMaterial({ color: COLORS.glass, transparent: true, opacity: 0.35, roughness: 0.1 });
    [8.75, 16.05].forEach((x) => court.add(mesh(box(0.06, 2.6, 4.2, 0.02), glassMaterial, { x, y: 1.35, z: -0.2, shadow: false })));
    court.add(mesh(box(0.04, 0.9, 4.2, 0.01), new THREE.MeshStandardMaterial({ color: 0xf8fafc, transparent: true, opacity: 0.7 }), { x: 12.4, y: 0.47, z: -0.2 }));
    [-2.2, 1.8].forEach((z) => court.add(mesh(new THREE.CylinderGeometry(0.04, 0.04, 1.0, 8), material(0x0f172a), { x: 12.4, y: 0.5, z })));
    wallSign(scene, texts.court, { x: 12.4 + PADEL_SHIFT, y: 3.05, width: 3.2 });

    actors.players = [[10.2, -Math.PI / 2 + Math.PI, 0xfacc15], [14.6, -Math.PI / 2, 0xa855f7]].map(([x, rotation, shirt], index) => {
        const player = character({ shirt, skin: index ? COLORS.skinLight : COLORS.skinDark, hair: index ? COLORS.hairBlond : COLORS.hairBlack });
        player.root.position.set(x, 0.05, 0.1);
        player.root.rotation.y = rotation;
        player.arms[1].add(racket());
        court.add(player.root);

        return player;
    });

    actors.ball = mesh(new THREE.SphereGeometry(0.07, 12, 10), material(0xd9f99d, { emissive: 0xbef264, emissiveIntensity: 0.3 }), { y: 1 });
    court.add(actors.ball);

    // Golf: a turf floor, a hitting bay with a net, where a golfer drives balls,
    // and a putting green with a flag for the member who comes in.
    scene.add(mesh(box(7.2, 0.04, 4.4, 0.02), material(COLORS.turf), { x: 25.7, y: 0.02, z: -0.2, shadow: false }));
    wallSign(scene, texts.golf, { x: 25.7, y: 3.05, width: 3.2 });
    scene.add(mesh(box(1.2, 0.03, 1.4, 0.01), material(0x166534), { x: 23.2, y: 0.05, z: -0.55, shadow: false }));

    const netMaterial = new THREE.MeshStandardMaterial({ color: 0xffffff, transparent: true, opacity: 0.35, roughness: 0.9, side: THREE.DoubleSide });
    scene.add(mesh(new THREE.PlaneGeometry(2.6, 2.4), netMaterial, { x: 24.9, y: 1.2, z: -0.9, shadow: false }).rotateY(Math.PI / 2));
    const netFrame = material(0x334155);
    [-2.2, 0.4].forEach((z) => scene.add(mesh(new THREE.CylinderGeometry(0.03, 0.03, 2.5, 8), netFrame, { x: 24.9, y: 1.25, z })));
    const netTop = mesh(new THREE.CylinderGeometry(0.03, 0.03, 2.6, 8), netFrame, { x: 24.9, y: 2.45, z: -0.9 });
    netTop.rotation.x = Math.PI / 2;
    scene.add(netTop);

    actors.golfer = character({ shirt: 0x0ea5e9, skin: COLORS.skinLight, hair: COLORS.hairBrown, pants: 0xe2e8f0 });
    actors.golfer.root.position.set(23.2, 0.05, -0.85);
    actors.golfer.arms[0].add(golfClub());
    scene.add(actors.golfer.root);

    actors.golfBall = mesh(new THREE.SphereGeometry(0.06, 12, 10), material(0xffffff), { x: 23.35, y: 0.1, z: -0.4 });
    scene.add(actors.golfBall);

    scene.add(mesh(new THREE.CylinderGeometry(1.35, 1.35, 0.03, 40), material(COLORS.puttingGreen), { x: 27.1, y: 0.05, z: -0.75, shadow: false }));
    scene.add(mesh(new THREE.CylinderGeometry(0.08, 0.08, 0.02, 20), material(0x0f172a), { x: PUTTING_HOLE.x, y: 0.07, z: PUTTING_HOLE.z, shadow: false }));
    scene.add(mesh(new THREE.CylinderGeometry(0.015, 0.015, 1.4, 8), material(0xf8fafc), { x: PUTTING_HOLE.x, y: 0.75, z: PUTTING_HOLE.z }));
    const flagShape = new THREE.Shape();
    flagShape.moveTo(0, 0);
    flagShape.lineTo(0.42, -0.14);
    flagShape.lineTo(0, -0.28);
    const flag = mesh(new THREE.ShapeGeometry(flagShape), material(0xef4444, { side: THREE.DoubleSide }), { x: PUTTING_HOLE.x + 0.01, y: 1.44, z: PUTTING_HOLE.z });
    scene.add(flag);
    actors.flag = flag;

    actors.puttBall = mesh(new THREE.SphereGeometry(0.06, 12, 10), material(0xffffff, { transparent: true }), { y: 0.1 });
    actors.puttBall.visible = false;
    scene.add(actors.puttBall);

    // Pendant lamps.
    [-10, -4.5, 0, 5, 10.9, 12.4 + PADEL_SHIFT, 25.7].forEach((x) => {
        scene.add(mesh(new THREE.CylinderGeometry(0.01, 0.01, 0.6, 6), material(0xffffff), { x, y: 3.3, z: -0.6, shadow: false }));
        scene.add(mesh(new THREE.SphereGeometry(0.24, 18, 10, 0, Math.PI * 2, 0, Math.PI / 2), material(0xffffff, { side: THREE.DoubleSide }), { x, y: 2.95, z: -0.6, shadow: false }));
        scene.add(mesh(new THREE.SphereGeometry(0.08, 10, 8), material(0xfef3c7, { emissive: 0xfde68a, emissiveIntensity: 1.2 }), { x, y: 2.97, z: -0.6, shadow: false }));
    });

    // Plants.
    [[-12.4, -1.8], [1.9, 1.7], [7.8, 1.7]].forEach(([x, z]) => {
        scene.add(mesh(new THREE.CylinderGeometry(0.24, 0.18, 0.44, 16), material(0xffffff), { x, y: 0.22, z }));
        scene.add(mesh(new THREE.SphereGeometry(0.3, 14, 10), material(0x34d399), { x, y: 0.75, z }));
    });

    // Members who arrive one after another, each with a phone showing the QR code.
    const looks = [
        { shirt: COLORS.hoodie, skin: COLORS.skinDark, hair: COLORS.hairBlack, hood: true },
        { shirt: 0x22c55e, skin: COLORS.skinLight, hair: COLORS.hairBlond },
        { shirt: 0xe11d48, skin: 0xd6a07a, hair: COLORS.hairBrown, hood: true },
        { shirt: 0x8b5cf6, skin: COLORS.skinDark, hair: COLORS.hairBrown },
        { shirt: 0x0d9488, skin: COLORS.skinLight, hair: COLORS.hairBlack, hood: true },
    ];

    actors.arrivals = texts.members.map((member, index) => {
        const person = character(looks[index % looks.length]);
        const phone = new THREE.Group();
        phone.position.set(0, -0.66, 0.06);
        phone.add(mesh(box(0.14, 0.26, 0.03, 0.02), material(0x0f172a)));
        const qr = new THREE.Mesh(new THREE.PlaneGeometry(0.11, 0.11), new THREE.MeshBasicMaterial({ map: qrTexture(), transparent: true }));
        qr.position.z = 0.017;
        phone.add(qr);
        person.arms[1].add(phone);
        person.materials.push(...phone.children.map((child) => child.material));

        if (member.activity === 'padel' || member.activity === 'golf') {
            const gear = member.activity === 'padel' ? racket() : golfClub({ length: 0.8 });
            person.arms[0].add(gear);
            gear.traverse((child) => child.material && person.materials.push(child.material));
        }

        person.materials.forEach((personMaterial) => (personMaterial.transparent = true));
        person.root.visible = false;
        scene.add(person.root);

        const bubble = labelSprite([member.greeting], { width: 420, height: 150 });
        bubble.position.set(-9.9, 2.45, -1.2);
        scene.add(bubble);

        const checkedIn = labelSprite([texts.checkedIn, member.label], { width: 600, height: 170, check: true });
        checkedIn.position.set(-8.6, 2.6, -0.2);
        scene.add(checkedIn);

        return { ...person, activity: member.activity, phone, bubble, checkedIn };
    });

    // Each member walks in once the previous one has reached their room.
    let start = 0;
    actors.arrivals.forEach((member) => {
        member.offset = start;
        start += settleSeconds(member.activity) + DWELL_SECONDS;
    });
    actors.cycle = start;

    return actors;
}

/**
 * Dancing: sway from side to side with the arms up in turn, and a spin every
 * fourth second.
 */
function dance(person, seconds, offset = 0) {
    const time = seconds + offset;
    const sway = Math.sin(time * Math.PI * 1.6);
    const spin = phase((time % 4) / 4, 0.78, 1);

    person.body.position.set(sway * 0.1, Math.abs(Math.cos(time * Math.PI * 1.6)) * 0.05, 0);
    person.body.rotation.set(0, spin * Math.PI * 2, -sway * 0.08);
    person.arms[0].rotation.set(0, 0, -(0.35 + 2.2 * Math.max(0, sway)));
    person.arms[1].rotation.set(0, 0, 0.35 + 2.2 * Math.max(0, -sway));
    person.legs[0].rotation.set(0, 0, -0.18 * Math.max(0, sway));
    person.legs[1].rotation.set(0, 0, 0.18 * Math.max(0, -sway));
}

/**
 * Yoga: a slow flow between tree pose (hands overhead, one leg out) and
 * warrior (arms wide, legs apart), with a gentle breath.
 */
function yoga(person, seconds, offset = 0) {
    const flow = smooth((1 - Math.cos((seconds + offset) * Math.PI / 4)) / 2);
    const tree = 1 - flow;

    person.arms[0].rotation.set(0, 0, -(1.5 + 1.45 * tree));
    person.arms[1].rotation.set(0, 0, 1.5 + 1.45 * tree);
    person.legs[0].rotation.set(0, 0, -0.38 * flow);
    person.legs[1].rotation.set(-0.35 * tree, 0, 0.38 * flow + 0.55 * tree);
    person.body.position.set(0, Math.sin(seconds * 1.2 + offset) * 0.015 - 0.06 * flow, 0);
    person.body.rotation.set(0, 0, 0);
}

/**
 * A golf swing seen from the front: hands together, the club goes back over
 * the right shoulder, through the ball and up into the follow-through.
 * Returns the moment of the loop (0..1), so the ball can fly at impact.
 */
const SWING_SECONDS = 3.2;
const IMPACT_AT = 0.43;

function golfSwing(person, seconds) {
    const moment = (seconds % SWING_SECONDS) / SWING_SECONDS;
    const back = phase(moment, 0, 0.36);
    const through = phase(moment, 0.38, 0.48);
    const finish = phase(moment, 0.7, 1);
    const swing = (-2.4 * back + 4.9 * through) * (1 - finish);

    // The arms lean towards each other, so the hands stay together on the grip
    // both below the waist and above the shoulders.
    const together = 0.32 * Math.cos(swing);

    person.arms[0].rotation.set(-0.3, 0, together + swing);
    person.arms[1].rotation.set(-0.3, 0, -together + swing);
    person.body.rotation.set(0, -0.12 * swing, 0);
    person.body.position.set(0, 0, 0);
    person.legs.forEach((leg) => leg.rotation.set(0, 0.12 * swing, 0));

    return moment;
}

/**
 * A putt: a short pendulum of the putter towards the hole.
 */
function putt(person, seconds) {
    const moment = (seconds % SWING_SECONDS) / SWING_SECONDS;
    const stroke = -0.3 * phase(moment, 0, 0.18) + 0.55 * phase(moment, 0.2, 0.28) - 0.25 * phase(moment, 0.6, 0.9);

    person.arms[0].rotation.set(-0.3, 0, 0.3 + stroke);
    person.arms[1].rotation.set(-0.3, 0, -0.3 + stroke);
    person.body.position.set(0, -0.04, 0);

    return moment;
}

function resetLimbs(person) {
    person.arms.forEach((arm) => arm.rotation.set(0, 0, 0));
    person.legs.forEach((leg) => leg.rotation.set(0, 0, 0));
    person.body.position.set(0, 0, 0);
    person.body.rotation.set(0, 0, 0);
}

/**
 * Places the actors for a moment: `time` is the position in the story loop
 * (0..1), `seconds` the running clock for the looping workouts.
 */
const STREET = new THREE.Vector3(-16.6, 0, 1.15);
const AT_DESK = new THREE.Vector3(-9.55, 0, 0.05);

/**
 * How far right the padel court sits from where it was built: the yoga studio
 * fills the gap.
 */
const PADEL_SHIFT = 5.4;

const PUTTING_HOLE = new THREE.Vector3(27.9, 0, -0.45);

/**
 * The lane along the front of the building towards the rooms on the right,
 * clear of the plants and the padel glass.
 */
const LANE_IN = new THREE.Vector3(-7.0, 0, 2.25);

/**
 * Where each activity happens: the route from the desk, the spot and the way
 * the member faces there.
 */
const ACTIVITIES = {
    dance: { route: [new THREE.Vector3(-3, 0, 1.15)], spot: new THREE.Vector3(-1.0, 0.04, -0.15), facing: Math.PI - 0.35 },
    gym: { route: [new THREE.Vector3(2.6, 0, 1.4)], spot: new THREE.Vector3(4.1, 0.2, 0.5), facing: -Math.PI / 2 },
    yoga: { route: [LANE_IN, new THREE.Vector3(10.9, 0, 2.25)], spot: new THREE.Vector3(10.9, 0.04, 0.45), facing: Math.PI },
    padel: {
        route: [LANE_IN, new THREE.Vector3(8.2 + PADEL_SHIFT, 0, 2.2), new THREE.Vector3(9.6 + PADEL_SHIFT, 0.05, 2.2)],
        spot: new THREE.Vector3(10.4 + PADEL_SHIFT, 0.05, 1.35),
        facing: Math.PI / 2,
    },
    golf: { route: [LANE_IN, new THREE.Vector3(26.4, 0, 2.25)], spot: new THREE.Vector3(26.4, 0.06, -1.0), facing: 0 },
};

const SCAN_SECONDS = 1.8;

/**
 * Position and heading along a route of points, walked at WALK_SPEED.
 */
function alongRoute(points, seconds) {
    let left = seconds * WALK_SPEED;

    for (let index = 1; index < points.length; index++) {
        const from = points[index - 1];
        const to = points[index];
        const length = from.distanceTo(to);

        if (left <= length || index === points.length - 1) {
            const progress = Math.min(1, left / length);

            return {
                position: from.clone().lerp(to, progress),
                heading: Math.atan2(to.x - from.x, to.z - from.z),
                done: progress >= 1,
            };
        }

        left -= length;
    }
}

function routeSeconds(points) {
    let length = 0;

    for (let index = 1; index < points.length; index++) {
        length += points[index - 1].distanceTo(points[index]);
    }

    return length / WALK_SPEED;
}

/**
 * Seconds from the street until a member stands at the spot of their activity.
 */
function settleSeconds(activity) {
    const { route, spot } = ACTIVITIES[activity];

    return routeSeconds([STREET, AT_DESK]) + SCAN_SECONDS + routeSeconds([AT_DESK, ...route, spot]);
}

function walkCycle(person, seconds) {
    const stride = Math.sin(seconds * Math.PI * 3.4) * 0.55;

    person.legs[0].rotation.x = stride;
    person.legs[1].rotation.x = -stride;
    person.arms[0].rotation.x = -stride * 0.7;
    person.arms[1].rotation.x = stride * 0.7;
    person.body.position.y = Math.abs(Math.sin(seconds * Math.PI * 3.4)) * 0.04;
}

/**
 * Places the actors for a moment on the running clock.
 */
function pose(actors, seconds) {
    const { staff, scannerMaterial, beamMaterial } = actors;
    let greeting = 0;
    let scanning = false;
    let accepted = false;

    // Padel rhythm, shared by the players and the ball.
    const rally = (seconds % 2.4) / 2.4;
    const swingFor = (hitAt) => {
        const distance = Math.min(Math.abs(rally - hitAt), Math.abs(rally - hitAt - 1), Math.abs(rally - hitAt + 1));

        return Math.max(0, 1 - distance / 0.12);
    };

    const { cycle } = actors;
    let putting = 0;

    actors.arrivals.forEach((member) => {
        const local = (seconds - member.offset + cycle * 10) % cycle;
        const activity = ACTIVITIES[member.activity];
        const inRoute = [STREET, AT_DESK];
        const onRoute = [AT_DESK, ...activity.route, activity.spot];
        const arriveAt = routeSeconds(inRoute);
        const leaveAt = arriveAt + SCAN_SECONDS;
        const settleAt = leaveAt + routeSeconds(onRoute);
        const fadeAt = cycle - 1.2;

        resetLimbs(member);
        member.root.visible = true;
        member.phone.visible = local < leaveAt + 0.8;

        const opacity = clamp01(local / 0.4) * (1 - clamp01((local - fadeAt) / 0.8));
        member.materials.forEach((personMaterial) => (personMaterial.opacity = opacity));
        member.root.visible = opacity > 0.01;

        if (local < arriveAt) {
            const walk = alongRoute(inRoute, local);
            member.root.position.copy(walk.position);
            member.root.rotation.y = walk.heading;
            walkCycle(member, seconds);
        } else if (local < leaveAt) {
            // At the desk: hold the phone over the scanner.
            const scan = local - arriveAt;
            const raise = clamp01(scan / 0.4) * (1 - clamp01((scan - SCAN_SECONDS + 0.4) / 0.4));
            member.root.position.copy(AT_DESK);
            member.root.rotation.y = Math.PI + 0.45;
            member.arms[1].rotation.x = -1.35 * raise;

            greeting = Math.max(greeting, clamp01(scan / 0.3));
            scanning = scanning || (scan > 0.4 && scan < 1.0);
            accepted = accepted || scan >= 1.0;
        } else if (local < settleAt) {
            const walk = alongRoute(onRoute, local - leaveAt);
            member.root.position.copy(walk.position);
            member.root.rotation.y = walk.heading;
            walkCycle(member, seconds);
        } else {
            const settled = clamp01((local - settleAt) / 0.5);
            member.root.position.copy(activity.spot);
            member.root.rotation.y = activity.facing;

            if (member.activity === 'dance') {
                dance(member, seconds, 0.3);
            } else if (member.activity === 'yoga') {
                yoga(member, seconds, 0.5);
            } else if (member.activity === 'golf') {
                putt(member, seconds);
                putting = Math.max(putting, settled * member.materials[0].opacity);
            } else if (member.activity === 'gym') {
                const run = Math.sin(seconds * Math.PI * 5) * settled;
                member.legs[0].rotation.x = run * 0.7;
                member.legs[1].rotation.x = -run * 0.7;
                member.arms[0].rotation.x = -run * 0.8;
                member.arms[1].rotation.x = run * 0.8;
                member.body.position.y = Math.abs(run) * 0.06;
            } else {
                member.arms[0].rotation.x = (-0.6 - 1.2 * swingFor(0)) * settled;
                member.root.position.z = activity.spot.z + Math.sin(seconds * 1.5 + 2) * 0.2;
            }
        }

        // Greeting and confirmation for this member only.
        const atDesk = local - arriveAt;
        const bubbleIn = clamp01((atDesk + 0.6) / 0.3) * (1 - clamp01((atDesk - 0.75) / 0.25));
        member.bubble.material.opacity = local < leaveAt ? bubbleIn : 0;
        member.bubble.scale.set(2.3 * (0.6 + 0.4 * member.bubble.material.opacity), 0.82 * (0.6 + 0.4 * member.bubble.material.opacity), 1);

        const checkIn = clamp01((atDesk - 1.0) / 0.25) * (1 - clamp01((atDesk - SCAN_SECONDS - 0.9) / 0.3));
        member.checkedIn.material.opacity = atDesk > 0 ? checkIn : 0;
        member.checkedIn.position.y = 2.5 + 0.15 * member.checkedIn.material.opacity;
        member.checkedIn.scale.set(3.2 * (0.7 + 0.3 * member.checkedIn.material.opacity), 0.907 * (0.7 + 0.3 * member.checkedIn.material.opacity), 1);
    });

    // The scanner: a flickering beam, then green.
    beamMaterial.opacity = scanning ? 0.3 + Math.abs(Math.sin(seconds * 30)) * 0.4 : 0;
    scannerMaterial.color.setHex(accepted ? COLORS.green : 0x1e3a8a);
    scannerMaterial.emissive.setHex(accepted ? COLORS.green : 0x1e3a8a);

    // The staff member turns to whoever is at the desk and waves.
    actors.greeting = actors.greeting === undefined ? greeting : actors.greeting + (greeting - actors.greeting) * 0.15;
    staff.arms[1].rotation.z = 2.6 * actors.greeting + Math.sin(seconds * 9) * 0.3 * actors.greeting;
    staff.body.rotation.y = 0.45 * actors.greeting;

    // The dance class, the teacher in front.
    dance(actors.instructor, seconds);
    actors.classmates.forEach((classmate, index) => dance(classmate, seconds, 0.1 + index * 0.08));

    // The yoga class flows together, the teacher leading.
    yoga(actors.yogaTeacher, seconds);
    actors.yogis.forEach((yogi, index) => yoga(yogi, seconds, 0.4 + index * 0.2));

    // Golf: a drive into the net, and the putt of the member who came in.
    const swing = golfSwing(actors.golfer, seconds);
    const ballFlight = clamp01((swing - IMPACT_AT) / 0.12);
    const ballDrop = clamp01((swing - IMPACT_AT - 0.12) / 0.15);
    const onTee = swing < IMPACT_AT || swing > 0.9;
    actors.golfBall.visible = swing < IMPACT_AT + 0.3 || swing > 0.9;
    actors.golfBall.position.set(
        onTee ? 23.35 : 23.35 + 1.45 * ballFlight,
        onTee ? 0.1 : 0.1 + Math.sin(ballFlight * Math.PI * 0.6) * 1.3 * (1 - ballDrop),
        onTee ? -0.4 : -0.4 - 0.3 * ballFlight,
    );
    actors.flag.rotation.y = Math.sin(seconds * 2.2) * 0.25;

    const puttMoment = (seconds % SWING_SECONDS) / SWING_SECONDS;
    const roll = phase(puttMoment, 0.26, 0.62);
    const sink = clamp01((puttMoment - 0.62) / 0.06);
    const start = ACTIVITIES.golf.spot.x + 0.15;
    actors.puttBall.visible = putting > 0.5 && puttMoment < 0.7;
    actors.puttBall.position.set(start + (PUTTING_HOLE.x - start) * roll, 0.1 - 0.08 * sink, PUTTING_HOLE.z);

    // The gym: running and curling.
    const run = Math.sin(seconds * Math.PI * 5);
    actors.runner.legs[0].rotation.x = run * 0.7;
    actors.runner.legs[1].rotation.x = -run * 0.7;
    actors.runner.arms[0].rotation.x = -run * 0.8;
    actors.runner.arms[1].rotation.x = run * 0.8;
    actors.runner.body.position.y = Math.abs(run) * 0.06;

    const curl = (1 - Math.cos(seconds * Math.PI)) / 2;
    actors.lifter.arms[0].rotation.x = -1.9 * curl;
    actors.lifter.arms[1].rotation.x = -1.9 * (1 - curl);

    // Padel: the ball flies from one racket to the other over the net.
    const towardsRight = rally < 0.5;
    const flight = towardsRight ? rally * 2 : (rally - 0.5) * 2;
    const [left, right] = actors.players;
    const fromX = towardsRight ? 10.7 : 14.1;
    const toX = towardsRight ? 14.1 : 10.7;
    actors.ball.position.set(fromX + (toX - fromX) * flight, 1.1 + Math.sin(flight * Math.PI) * 1.4, 0.1 + Math.sin(flight * Math.PI) * 0.3);

    left.arms[1].rotation.x = -0.6 - 1.2 * swingFor(0);
    right.arms[1].rotation.x = -0.6 - 1.2 * swingFor(0.5);
    left.root.position.z = 0.1 + Math.sin(seconds * 1.3) * 0.25;
    right.root.position.z = 0.1 + Math.sin(seconds * 1.1 + 1) * 0.25;
}

function mount(container) {
    const canvas = document.createElement('canvas');
    canvas.className = 'pointer-events-none absolute inset-0 size-full opacity-0 transition-opacity duration-700';

    let renderer;

    try {
        renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
    } catch (error) {
        // No WebGL: show the still SVG instead.
        container.dataset.heroClubFallback = 'true';

        return;
    }

    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.NeutralToneMapping;

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(22, 1, 0.5, 200);
    const tilt = 0.32;

    scene.add(new THREE.HemisphereLight(0xeef2ff, 0x6366f1, 1.6));

    const sun = new THREE.DirectionalLight(0xfff7ed, 2.0);
    sun.position.set(10, 12, 8);
    sun.target.position.set(6, 0, 0);
    scene.add(sun.target);
    sun.castShadow = true;
    sun.shadow.mapSize.set(4096, 1024);
    sun.shadow.camera.left = -25;
    sun.shadow.camera.right = 25;
    sun.shadow.camera.top = 8;
    sun.shadow.camera.bottom = -8;
    sun.shadow.radius = 4;
    sun.shadow.bias = -0.0005;
    scene.add(sun);

    const actors = buildScene(scene, {
        club: container.dataset.club,
        studio: container.dataset.studio,
        yoga: container.dataset.yoga,
        court: container.dataset.court,
        golf: container.dataset.golf,
        checkedIn: container.dataset.checkedIn,
        members: JSON.parse(container.dataset.members),
    });

    let distance = 40;
    let panRange = 0;

    /**
     * Show as much of the building as fits at a readable size, up to
     * MAX_VISIBLE_WIDTH, and pan along the rest. A lens shift moves the
     * building below the hero text.
     */
    const resize = () => {
        const bounds = container.getBoundingClientRect();
        const { width, height } = bounds;
        const aspect = width / height;
        const halfFov = THREE.MathUtils.degToRad(camera.fov / 2);
        const sceneWidth = SCENE_RIGHT - SCENE_LEFT;
        const widest = Math.min(sceneWidth + 2, MAX_VISIBLE_WIDTH);
        let visibleWidth = aspect >= 1.3 ? widest : Math.min(widest, Math.max(11, 14 * aspect));

        // Fit the building into the space below the hero text: zoom out on
        // short screens rather than run behind the buttons.
        const text = document.querySelector('[data-hero-text]');
        const textBottom = text ? text.getBoundingClientRect().bottom - bounds.top + 24 : height * 0.6;
        const available = Math.max(120, height - textBottom - BOTTOM_MARGIN);
        visibleWidth = Math.min(widest, Math.max(visibleWidth, (BUILDING_HEIGHT * width) / available));

        // Put the building right at the bottom of the backdrop.
        const band = (BUILDING_HEIGHT * width) / visibleWidth;
        const shift = (height - BOTTOM_MARGIN - band / 2) / height - 0.5;

        renderer.setSize(width, height, false);
        camera.aspect = aspect;
        distance = visibleWidth / aspect / (2 * Math.tan(halfFov));
        panRange = Math.max(0, (sceneWidth - visibleWidth + 2) / 2);
        camera.clearViewOffset();
        camera.setViewOffset(width, height, 0, -height * shift, width, height);
        camera.updateProjectionMatrix();
    };

    resize();
    new ResizeObserver(resize).observe(container);

    const center = (SCENE_LEFT + SCENE_RIGHT) / 2;
    const clock = new THREE.Clock();
    let cameraX = null;
    let lastSeconds = 0;

    /**
     * When the building is wider than the screen, the camera follows the member
     * who arrived last, from the street via the desk to their activity.
     */
    const tourX = (seconds) => {
        const newest = actors.arrivals.reduce((latest, member) => {
            const local = (seconds - member.offset + actors.cycle * 10) % actors.cycle;

            return local < latest.local ? { local, member } : latest;
        }, { local: Infinity, member: null }).member;

        return Math.min(center + panRange, Math.max(center - panRange, newest.root.position.x));
    };
    let visible = true;

    new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
    }).observe(container);

    const render = () => {
        const seconds = clock.getElapsedTime();
        pose(actors, seconds);

        const targetX = panRange > 0 ? tourX(seconds) : center + Math.sin(seconds * 0.11) * 0.4;
        cameraX = cameraX === null ? targetX : cameraX + (targetX - cameraX) * Math.min(1, (seconds - lastSeconds) * 2.2);
        lastSeconds = seconds;
        const x = cameraX;

        camera.position.set(x, 1.6 + distance * Math.sin(tilt), distance * Math.cos(tilt));
        camera.lookAt(x, 1.4, 0);

        renderer.render(scene, camera);
    };

    renderer.setAnimationLoop(() => {
        if (visible && ! document.hidden) {
            render();
        }
    });

    render();
    container.appendChild(canvas);
    container.dataset.heroClubReady = 'true';

    // Fade the scene in once its first frame is on the canvas.
    requestAnimationFrame(() => canvas.classList.remove('opacity-0'));
}

function boot() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    document.querySelectorAll('[data-hero-club]').forEach((container) => {
        // Draw the labels in Poppins once the font is there.
        document.fonts.ready.then(() => mount(container));
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}

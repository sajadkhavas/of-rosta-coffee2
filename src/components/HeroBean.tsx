import { useEffect, useRef } from "react";
import * as THREE from "three";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";

export default function HeroBean() {
  const mountRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const mount = mountRef.current;
    if (!mount) return;
    const w = mount.clientWidth || 400;
    const h = mount.clientHeight || 400;

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(50, w / h, 0.1, 100);
    camera.position.z = 3.5;

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setSize(w, h);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.setClearColor(0x000000, 0);
    mount.appendChild(renderer.domElement);

    scene.add(new THREE.AmbientLight(0xc8965a, 0.4));
    const p1 = new THREE.PointLight(0xc8965a, 2, 10);
    p1.position.set(2, 2, 2);
    scene.add(p1);
    const p2 = new THREE.PointLight(0x4a2c0a, 1, 10);
    p2.position.set(-2, -1, 1);
    scene.add(p2);
    const rim = new THREE.DirectionalLight(0xf7f0e6, 0.4);
    rim.position.set(0, 0, -3);
    scene.add(rim);

    const beanGeo = new THREE.SphereGeometry(1, 64, 64);
    beanGeo.scale(0.7, 1, 0.5);
    const beanMat = new THREE.MeshStandardMaterial({
      color: 0x3d1a00,
      roughness: 0.45,
      metalness: 0.15,
    });
    const bean = new THREE.Mesh(beanGeo, beanMat);
    scene.add(bean);

    const creasePath = new THREE.CatmullRomCurve3([
      new THREE.Vector3(0, -0.95, 0),
      new THREE.Vector3(0.05, -0.5, 0.05),
      new THREE.Vector3(0.03, 0, 0.06),
      new THREE.Vector3(0.05, 0.5, 0.05),
      new THREE.Vector3(0, 0.95, 0),
    ]);
    const creaseGeo = new THREE.TubeGeometry(creasePath, 40, 0.04, 12, false);
    const crease = new THREE.Mesh(
      creaseGeo,
      new THREE.MeshStandardMaterial({ color: 0x1a0800, roughness: 0.8 }),
    );
    scene.add(crease);

    // Steam
    const N = 80;
    const positions = new Float32Array(N * 3);
    for (let i = 0; i < N; i++) {
      positions[i * 3] = (Math.random() - 0.5) * 0.6;
      positions[i * 3 + 1] = Math.random() * 2 + 1;
      positions[i * 3 + 2] = (Math.random() - 0.5) * 0.6;
    }
    const pg = new THREE.BufferGeometry();
    pg.setAttribute("position", new THREE.BufferAttribute(positions, 3));
    const particles = new THREE.Points(
      pg,
      new THREE.PointsMaterial({ color: 0xc8965a, size: 0.025, transparent: true, opacity: 0.6 }),
    );
    scene.add(particles);

    const group = new THREE.Group();
    scene.remove(bean);
    scene.remove(crease);
    group.add(bean);
    group.add(crease);
    scene.add(group);

    const floatTween = gsap.to(group.position, {
      y: 0.12,
      duration: 3,
      yoyo: true,
      repeat: -1,
      ease: "sine.inOut",
    });
    const spinTween = gsap.to(group.rotation, {
      y: Math.PI * 2,
      duration: 14,
      repeat: -1,
      ease: "none",
    });

    const st = ScrollTrigger.create({
      trigger: "#hero-section",
      start: "top top",
      end: "bottom top",
      scrub: 2,
      onUpdate: (self) => {
        group.rotation.x = self.progress * Math.PI * 0.5;
        camera.position.z = 3.5 - self.progress * 1.2;
      },
    });

    let frame = 0;
    const animate = () => {
      frame = requestAnimationFrame(animate);
      const pos = pg.attributes.position;
      for (let i = 0; i < N; i++) {
        let y = pos.getY(i) + 0.003;
        if (y > 3) y = 1;
        pos.setY(i, y);
      }
      pos.needsUpdate = true;
      renderer.render(scene, camera);
    };
    animate();

    const onResize = () => {
      const nw = mount.clientWidth;
      const nh = mount.clientHeight;
      renderer.setSize(nw, nh);
      camera.aspect = nw / nh;
      camera.updateProjectionMatrix();
    };
    window.addEventListener("resize", onResize);

    return () => {
      cancelAnimationFrame(frame);
      window.removeEventListener("resize", onResize);
      floatTween.kill();
      spinTween.kill();
      st.kill();
      renderer.dispose();
      beanGeo.dispose();
      creaseGeo.dispose();
      pg.dispose();
      if (renderer.domElement.parentNode === mount) mount.removeChild(renderer.domElement);
    };
  }, []);

  return (
    <div
      ref={mountRef}
      aria-hidden
      className="absolute inset-0"
      style={{ pointerEvents: "none", willChange: "transform" }}
    />
  );
}

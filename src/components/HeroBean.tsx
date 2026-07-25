import { useEffect, useRef } from "react";

export default function HeroBean() {
  const mountRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const mount = mountRef.current;
    if (!mount) return;
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;

    let disposed = false;
    let disposeRuntime: (() => void) | undefined;

    const initialize = async () => {
      const [THREE, gsapModule, scrollTriggerModule] = await Promise.all([
        import("three"),
        import("gsap"),
        import("gsap/ScrollTrigger"),
      ]);
      if (disposed) return;

      const gsap = gsapModule.default;
      const { ScrollTrigger } = scrollTriggerModule;
      gsap.registerPlugin(ScrollTrigger);

      const width = mount.clientWidth || 400;
      const height = mount.clientHeight || 400;
      const scene = new THREE.Scene();
      const camera = new THREE.PerspectiveCamera(50, width / height, 0.1, 100);
      camera.position.z = 3.5;

      const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
      renderer.setSize(width, height);
      renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
      renderer.setClearColor(0x000000, 0);
      mount.appendChild(renderer.domElement);

      scene.add(new THREE.AmbientLight(0xc8965a, 0.4));
      const primaryLight = new THREE.PointLight(0xc8965a, 2, 10);
      primaryLight.position.set(2, 2, 2);
      scene.add(primaryLight);
      const secondaryLight = new THREE.PointLight(0x4a2c0a, 1, 10);
      secondaryLight.position.set(-2, -1, 1);
      scene.add(secondaryLight);
      const rimLight = new THREE.DirectionalLight(0xf7f0e6, 0.4);
      rimLight.position.set(0, 0, -3);
      scene.add(rimLight);

      const beanGeometry = new THREE.SphereGeometry(1, 64, 64);
      beanGeometry.scale(0.7, 1, 0.5);
      const beanMaterial = new THREE.MeshStandardMaterial({
        color: 0x3d1a00,
        roughness: 0.45,
        metalness: 0.15,
      });
      const bean = new THREE.Mesh(beanGeometry, beanMaterial);

      const creasePath = new THREE.CatmullRomCurve3([
        new THREE.Vector3(0, -0.95, 0),
        new THREE.Vector3(0.05, -0.5, 0.05),
        new THREE.Vector3(0.03, 0, 0.06),
        new THREE.Vector3(0.05, 0.5, 0.05),
        new THREE.Vector3(0, 0.95, 0),
      ]);
      const creaseGeometry = new THREE.TubeGeometry(creasePath, 40, 0.04, 12, false);
      const creaseMaterial = new THREE.MeshStandardMaterial({
        color: 0x1a0800,
        roughness: 0.8,
      });
      const crease = new THREE.Mesh(creaseGeometry, creaseMaterial);

      const particleCount = 80;
      const positions = new Float32Array(particleCount * 3);
      for (let index = 0; index < particleCount; index += 1) {
        positions[index * 3] = (Math.random() - 0.5) * 0.6;
        positions[index * 3 + 1] = Math.random() * 2 + 1;
        positions[index * 3 + 2] = (Math.random() - 0.5) * 0.6;
      }
      const particleGeometry = new THREE.BufferGeometry();
      particleGeometry.setAttribute("position", new THREE.BufferAttribute(positions, 3));
      const particleMaterial = new THREE.PointsMaterial({
        color: 0xc8965a,
        size: 0.025,
        transparent: true,
        opacity: 0.6,
      });
      const particles = new THREE.Points(particleGeometry, particleMaterial);
      scene.add(particles);

      const group = new THREE.Group();
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
      const scrollTrigger = ScrollTrigger.create({
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
        const positionAttribute = particleGeometry.attributes.position;
        for (let index = 0; index < particleCount; index += 1) {
          let y = positionAttribute.getY(index) + 0.003;
          if (y > 3) y = 1;
          positionAttribute.setY(index, y);
        }
        positionAttribute.needsUpdate = true;
        renderer.render(scene, camera);
      };
      animate();

      const onResize = () => {
        const nextWidth = mount.clientWidth;
        const nextHeight = mount.clientHeight;
        if (!nextWidth || !nextHeight) return;
        renderer.setSize(nextWidth, nextHeight);
        camera.aspect = nextWidth / nextHeight;
        camera.updateProjectionMatrix();
      };
      window.addEventListener("resize", onResize);

      disposeRuntime = () => {
        cancelAnimationFrame(frame);
        window.removeEventListener("resize", onResize);
        floatTween.kill();
        spinTween.kill();
        scrollTrigger.kill();
        renderer.dispose();
        beanGeometry.dispose();
        beanMaterial.dispose();
        creaseGeometry.dispose();
        creaseMaterial.dispose();
        particleGeometry.dispose();
        particleMaterial.dispose();
        if (renderer.domElement.parentNode === mount) {
          mount.removeChild(renderer.domElement);
        }
      };
    };

    void initialize().catch(() => undefined);

    return () => {
      disposed = true;
      disposeRuntime?.();
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

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Kosmos" icon="heroicon-o-globe-alt" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Wirkungsräume', 'route' => 'seo.portfolios'],
            ['label' => $portfolio->name],
            ['label' => 'Kosmos'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
    </x-slot>

    <x-ui-page-container>
        @php($meta = $graph['meta'] ?? [])
        @if(!empty($meta['empty']))
            <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                <div class="text-[13px] text-gray-500">{{ $meta['reason'] ?? 'Noch keine semantische Karte.' }}</div>
                <a href="{{ route('seo.portfolios.show', $portfolio) }}" class="inline-block mt-3 text-[13px] font-medium text-[#166EE1] hover:underline">→ zurück zum Wirkungsraum</a>
            </div>
        @else
        <style>
            .kosmos-shell{position:relative; height:calc(100vh - 150px); min-height:520px; border-radius:14px; overflow:hidden;
                background:radial-gradient(120% 120% at 50% 15%, #0d1424 0%, #070a12 60%, #04060c 100%); border:1px solid #1b2436}
            #cosmos{position:absolute; inset:0}
            .k-top{position:absolute; top:0; left:0; right:0; z-index:6; display:flex; align-items:center; gap:16px;
                padding:12px 16px; background:linear-gradient(#04060cd9,#04060c00); pointer-events:none; flex-wrap:wrap}
            .k-top h1{margin:0; font-size:14px; font-weight:600; color:#e8eef7; letter-spacing:-.01em}
            .k-top .sub{font-family:ui-monospace,monospace; font-size:10.5px; color:#8ea0bd; letter-spacing:.02em}
            .k-legend{display:flex; gap:14px; align-items:center; font-size:11.5px; color:#c3cfe2}
            .k-legend .lk{display:flex; align-items:center; gap:6px}
            .k-legend .sw{width:10px; height:10px; border-radius:99px; box-shadow:0 0 8px currentColor}
            .k-legend .n{font-family:ui-monospace,monospace; font-size:10px; color:#7f8fac}
            .k-hint{margin-left:auto; font-size:11px; color:#7f8fac; pointer-events:auto}
            .k-hint a{color:#7aa2ff; text-decoration:none} .k-hint a:hover{text-decoration:underline}

            .k-panel{position:absolute; top:14px; right:14px; bottom:14px; width:340px; z-index:7;
                background:#0b1120ee; border:1px solid #22304d; border-radius:12px; backdrop-filter:blur(6px);
                display:flex; flex-direction:column; transform:translateX(calc(100% + 20px)); transition:transform .32s cubic-bezier(.2,.7,.2,1)}
            .k-panel.open{transform:translateX(0)}
            .k-ph{padding:16px 16px 14px; border-bottom:1px solid #1c2740}
            .k-ph .eyebrow{font-family:ui-monospace,monospace; font-size:10px; text-transform:uppercase; letter-spacing:.09em; color:#7f8fac}
            .k-ph h2{margin:5px 0 0; font-size:17px; color:#eaf0fa; letter-spacing:-.01em; line-height:1.2}
            .k-ph .q{font-size:11px; color:#8ea0bd; margin-top:3px}
            .k-close{position:absolute; top:13px; right:14px; background:none; border:0; color:#7f8fac; font-size:19px; cursor:pointer; line-height:1}
            .k-stats{display:grid; grid-template-columns:1fr 1fr; gap:10px; padding:14px 16px}
            .k-stat{background:#0f1830; border:1px solid #1c2740; border-radius:9px; padding:9px 11px}
            .k-stat .v{font-family:ui-monospace,monospace; font-size:15px; font-weight:600; color:#eaf0fa; font-variant-numeric:tabular-nums}
            .k-stat .l{font-size:9.5px; text-transform:uppercase; letter-spacing:.06em; color:#7f8fac; margin-top:2px}
            .k-wg{padding:2px 16px 12px}
            .k-wg .bar{height:6px; border-radius:99px; background:#0f1830; overflow:hidden; margin-top:6px}
            .k-wg .bar>i{display:block; height:100%; border-radius:99px}
            .k-wg .lab{display:flex; justify-content:space-between; font-size:10.5px; color:#8ea0bd}
            .k-kw{padding:4px 16px 16px; overflow-y:auto; flex:1}
            .k-kw .t{font-size:10px; text-transform:uppercase; letter-spacing:.07em; color:#7f8fac; margin-bottom:8px}
            .k-kw .chips{display:flex; flex-wrap:wrap; gap:5px}
            .k-kw .chips span{font-size:11px; background:#0f1830; border:1px solid #1c2740; color:#c3cfe2; padding:3px 8px; border-radius:6px}
            .k-act{padding:12px 16px; border-top:1px solid #1c2740; display:flex; gap:8px}
            .k-btn{font-size:12px; font-weight:560; padding:8px 12px; border-radius:8px; cursor:pointer; border:1px solid transparent; text-decoration:none; text-align:center}
            .k-btn.primary{background:#1f6feb; color:#fff} .k-btn.primary:hover{background:#1a5fd0}
            .k-btn.ghost{background:none; border-color:#2a3a5c; color:#c3cfe2} .k-btn.ghost:hover{border-color:#3a4e78}
            .k-badge{display:inline-block; font-family:ui-monospace,monospace; font-size:9.5px; padding:2px 7px; border-radius:5px; margin-top:8px}
            .kosmos-tip{font:12px/1.4 -apple-system,sans-serif; color:#eaf0fa; background:#0b1120f2; border:1px solid #22304d;
                padding:7px 9px; border-radius:8px; box-shadow:0 8px 24px #000a}
            .kosmos-tip b{color:#fff} .kosmos-tip .m{font-family:ui-monospace,monospace; font-size:10px; color:#8ea0bd}
        </style>

        <div class="kosmos-shell">
            <div id="cosmos"></div>

            <div class="k-top">
                <h1>{{ $portfolio->name }} · Kosmos</h1>
                <span class="sub">{{ $meta['counts']['nodes'] ?? 0 }} Themen · Größe = Potenzial · Leuchten = Wirkungsgrad</span>
                <div class="k-legend">
                    <div class="lk" style="color:#2f9e44"><span class="sw"></span> Weißraum <span class="n">{{ $meta['counts']['white'] ?? 0 }}</span></div>
                    <div class="lk" style="color:#14b8a6"><span class="sw"></span> besetzt <span class="n">{{ $meta['counts']['own'] ?? 0 }}</span></div>
                    <div class="lk" style="color:#b3a794"><span class="sw"></span> Grau <span class="n">{{ $meta['counts']['grau'] ?? 0 }}</span></div>
                </div>
                <div class="k-hint">Ziehen = drehen · Rad = zoom · Klick = Thema · <a href="{{ route('seo.portfolios.show', $portfolio) }}">← Wirkungsraum</a></div>
            </div>

            <aside class="k-panel" id="kpanel">
                <div class="k-ph" style="position:relative">
                    <button class="k-close" id="kclose" aria-label="schließen">×</button>
                    <div class="eyebrow" id="k-type">Thema</div>
                    <h2 id="k-name">—</h2>
                    <div class="q" id="k-quarter"></div>
                    <span class="k-badge" id="k-landbadge"></span>
                </div>
                <div class="k-stats">
                    <div class="k-stat"><div class="v" id="k-pot">—</div><div class="l">Potenzial</div></div>
                    <div class="k-stat"><div class="v" id="k-ist">—</div><div class="l">IST</div></div>
                    <div class="k-stat"><div class="v" id="k-gap">—</div><div class="l">Lücke</div></div>
                    <div class="k-stat"><div class="v" id="k-kw">—</div><div class="l">Keywords</div></div>
                </div>
                <div class="k-wg">
                    <div class="lab"><span>Wirkungsgrad</span><span id="k-wg-pct">—</span></div>
                    <div class="bar"><i id="k-wg-bar"></i></div>
                </div>
                <div class="k-kw">
                    <div class="t">Keywords im Thema</div>
                    <div class="chips" id="k-chips"></div>
                </div>
                <div class="k-act" id="k-actions"></div>
            </aside>
        </div>

        <script>window.__KOSMOS__ = @json($graph); window.__KOSMOS_BACK__ = @json(route('seo.portfolios.show', $portfolio));</script>
        <script type="module">
            import * as THREE from 'https://esm.sh/three@0.179.0';
            import ForceGraph3D from 'https://esm.sh/3d-force-graph@1.80.0?deps=three@0.179.0';

            const LAND = { own:0x14b8a6, white:0x2f9e44, grau:0xb3a794 };
            const LANDLABEL = { own:'besetzt — wir ranken', white:'Weißraum — baubar, noch nicht besetzt', grau:'Grau — Wettbewerber / erobern' };
            const LANDCSS = { own:'#14b8a6', white:'#2f9e44', grau:'#b3a794' };
            const fmt = n => n>=1000 ? (n/1000).toFixed(n>=10000?0:1).replace('.',',')+'k' : String(Math.round(n||0));

            function boot(){
                const el = document.getElementById('cosmos');
                if(!el || el.__booted) return; el.__booted = true;
                init(el);
            }

            function makeLabel(text, r){
                const c = document.createElement('canvas'), ct = c.getContext('2d');
                const fs = 30; ct.font = `600 ${fs}px -apple-system, sans-serif`;
                const w = Math.ceil(ct.measureText(text).width);
                c.width = w + 20; c.height = fs + 14;
                ct.font = `600 ${fs}px -apple-system, sans-serif`; ct.textBaseline = 'middle';
                ct.fillStyle = 'rgba(232,238,247,0.94)'; ct.fillText(text, 10, c.height/2);
                const tex = new THREE.CanvasTexture(c); tex.minFilter = THREE.LinearFilter;
                const spr = new THREE.Sprite(new THREE.SpriteMaterial({ map:tex, transparent:true, depthWrite:false }));
                const s = 0.12; spr.scale.set(c.width*s, c.height*s, 1); spr.position.y = -(r + 3.5);
                return spr;
            }

            function nodeObject(node){
                const g = new THREE.Group();
                const r = 3 + Math.sqrt(node.val) / 5.5;
                node.__r = r;
                const col = LAND[node.landtype] ?? LAND.white;
                const emissive = 0.12 + (node.wirkungsgrad||0)*0.5 + (node.adopted ? 0.22 : 0);
                const sphere = new THREE.Mesh(
                    new THREE.SphereGeometry(r, 26, 26),
                    new THREE.MeshPhongMaterial({ color:col, emissive:col, emissiveIntensity:emissive,
                        shininess: node.adopted ? 120 : 60, transparent:true, opacity: node.landtype==='grau' ? 0.72 : 0.96 })
                );
                g.add(sphere);

                // Zielerreichungs-Ring: Bogen = Wirkungsgrad, Farbe nach Höhe
                const wg = node.wirkungsgrad || 0;
                if(wg > 0.02){
                    const arc = Math.max(0.06, Math.min(1, wg)) * Math.PI * 2;
                    const rc = wg>=0.5 ? 0x22c55e : (wg>=0.2 ? 0xf59e0b : 0xef4444);
                    const ring = new THREE.Mesh(
                        new THREE.TorusGeometry(r*1.55, Math.max(.28, r*0.09), 8, 48, arc),
                        new THREE.MeshBasicMaterial({ color:rc, transparent:true, opacity:0.9 })
                    );
                    ring.rotation.x = Math.PI/2; ring.rotation.z = -Math.PI/2;
                    g.add(ring);
                }

                // adoptiert → gezündeter Stern (Halo)
                if(node.adopted){
                    const halo = new THREE.Mesh(
                        new THREE.SphereGeometry(r*2.0, 16, 16),
                        new THREE.MeshBasicMaterial({ color:col, transparent:true, opacity:0.14,
                            blending:THREE.AdditiveBlending, depthWrite:false })
                    );
                    g.add(halo);
                }

                if(node.adopted || node.val > 900) g.add(makeLabel(node.name, r));
                return g;
            }

            function init(el){
                const data = window.__KOSMOS__ || {nodes:[],links:[]};
                const Graph = ForceGraph3D()(el)
                    .backgroundColor('rgba(0,0,0,0)')
                    .graphData({ nodes:data.nodes, links:data.links.map(l=>({...l})) })
                    .nodeThreeObject(nodeObject)
                    .nodeLabel(n => `<div class="kosmos-tip"><b>${n.name}</b><br><span class="m">${n.kw} KW · Pot ${fmt(n.potenzial)} · IST ${fmt(n.ist)} · WG ${Math.round((n.wirkungsgrad||0)*100)}%</span></div>`)
                    .linkColor(() => 'rgba(150,170,210,0.22)')
                    .linkWidth(l => 0.4 + (l.w||1)*0.2)
                    .linkOpacity(0.35)
                    .linkDirectionalParticles(1)
                    .linkDirectionalParticleWidth(0.7)
                    .linkDirectionalParticleSpeed(0.004)
                    .linkDirectionalParticleColor(() => 'rgba(180,200,240,0.5)')
                    .onNodeClick(node => { focusNode(Graph, node); showPanel(node); })
                    .onBackgroundClick(() => hidePanel());

                // Kräfte etwas lockern für einen luftigen Kosmos
                Graph.d3Force('charge').strength(-120);
                if(Graph.d3Force('link')) Graph.d3Force('link').distance(l => 26 + (l.w||1)*8);

                // Weltraum: Sterne + Licht
                const scene = Graph.scene();
                const starsGeo = new THREE.BufferGeometry();
                const pos = new Float32Array(2600*3);
                for(let i=0;i<2600;i++){ pos[i*3]=(Math.random()-0.5)*2600; pos[i*3+1]=(Math.random()-0.5)*2600; pos[i*3+2]=(Math.random()-0.5)*2600; }
                starsGeo.setAttribute('position', new THREE.BufferAttribute(pos,3));
                scene.add(new THREE.Points(starsGeo, new THREE.PointsMaterial({ color:0x9aa7c4, size:1.1, transparent:true, opacity:0.55, sizeAttenuation:true })));
                scene.add(new THREE.AmbientLight(0x33436b, 0.85));
                const dir = new THREE.DirectionalLight(0xffffff, 0.7); dir.position.set(120,180,140); scene.add(dir);

                // sanftes Auto-Rotate bis zur ersten Interaktion
                const controls = Graph.controls();
                if(controls){ controls.autoRotate = true; controls.autoRotateSpeed = 0.35;
                    el.addEventListener('pointerdown', () => { controls.autoRotate = false; }, { once:true }); }

                const ro = new ResizeObserver(() => { Graph.width(el.clientWidth); Graph.height(el.clientHeight); });
                ro.observe(el);
                Graph.width(el.clientWidth); Graph.height(el.clientHeight);
            }

            function focusNode(Graph, node){
                const d = 60; const r = Math.hypot(node.x||0, node.y||0, node.z||1) || 1;
                const k = 1 + d / r;
                Graph.cameraPosition({ x:(node.x||0)*k, y:(node.y||0)*k, z:(node.z||0)*k }, node, 800);
            }

            function showPanel(node){
                const p = document.getElementById('kpanel');
                document.getElementById('k-type').textContent = node.adopted ? 'Cluster · besetzt' : 'Kandidaten-Thema';
                document.getElementById('k-name').textContent = node.name;
                document.getElementById('k-quarter').textContent = node.quarter ? ('Quartier: ' + node.quarter) : '';
                const lb = document.getElementById('k-landbadge');
                lb.textContent = LANDLABEL[node.landtype] || node.landtype;
                lb.style.background = (LANDCSS[node.landtype]||'#888') + '2e'; lb.style.color = LANDCSS[node.landtype] || '#ccc';
                document.getElementById('k-pot').textContent = fmt(node.potenzial);
                document.getElementById('k-ist').textContent = fmt(node.ist);
                document.getElementById('k-gap').textContent = fmt(node.gap);
                document.getElementById('k-kw').textContent = node.kw;
                const pct = Math.round((node.wirkungsgrad||0)*100);
                document.getElementById('k-wg-pct').textContent = pct + '%';
                const bar = document.getElementById('k-wg-bar');
                bar.style.width = Math.max(3, pct) + '%';
                bar.style.background = pct>=50 ? '#22c55e' : (pct>=20 ? '#f59e0b' : '#ef4444');
                const chips = (node.kw_sample||[]).map(k => `<span>${k}</span>`).join('') || '<span style="opacity:.5">—</span>';
                document.getElementById('k-chips').innerHTML = chips;
                const act = document.getElementById('k-actions');
                act.innerHTML = node.adopted
                    ? `<a class="k-btn primary" href="/seo/clusters/${node.cluster_id}">Cluster öffnen →</a>`
                    : `<a class="k-btn ghost" href="${window.__KOSMOS_BACK__}">im Wirkungsraum übernehmen →</a>`;
                p.classList.add('open');
            }
            function hidePanel(){ document.getElementById('kpanel').classList.remove('open'); }
            document.getElementById('kclose').addEventListener('click', hidePanel);

            document.addEventListener('DOMContentLoaded', boot);
            document.addEventListener('livewire:navigated', boot);
            boot();
        </script>
        @endif
    </x-ui-page-container>
</x-ui-page>

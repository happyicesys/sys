import{y as E,i as z,C as G,k as C,x as X,D as J,o as r,g as d,d as e,j as Y,B as e1,r as D,q as N,K as T,a as f,w as i,n as x,b as u,M as t1,c as b,L as k,l as H,F as M,m as L,f as w,t as _,p as n1,N as R}from"./app.5eac47b8.js";import{t as K,c as s1,u as I,l as O,V as j,o as $,p as a1,R as F,a as B}from"./open-closed.ee35a891.js";import{b as r1}from"./use-resolve-button-type.aa6e7c06.js";import{r as o1}from"./RectangleStackIcon.1a60db10.js";var l1=(n=>(n[n.Open=0]="Open",n[n.Closed=1]="Closed",n))(l1||{});let Q=Symbol("DisclosureContext");function V(n){let o=J(Q,null);if(o===null){let c=new Error(`<${n} /> is missing a parent <Disclosure /> component.`);throw Error.captureStackTrace&&Error.captureStackTrace(c,V),c}return o}let W=Symbol("DisclosurePanelContext");function c1(){return J(W,null)}let U=E({name:"Disclosure",props:{as:{type:[Object,String],default:"template"},defaultOpen:{type:[Boolean],default:!1}},setup(n,{slots:o,attrs:c}){let m=`headlessui-disclosure-button-${K()}`,s=`headlessui-disclosure-panel-${K()}`,l=z(n.defaultOpen?0:1),h=z(null),t=z(null),p={buttonId:m,panelId:s,disclosureState:l,panel:h,button:t,toggleDisclosure(){l.value=I(l.value,{[0]:1,[1]:0})},closeDisclosure(){l.value!==1&&(l.value=1)},close(a){p.closeDisclosure();let v=(()=>a?a instanceof HTMLElement?a:a.value instanceof HTMLElement?$(a):$(p.button):$(p.button))();v==null||v.focus()}};return G(Q,p),s1(C(()=>I(l.value,{[0]:O.Open,[1]:O.Closed}))),()=>{let{defaultOpen:a,...v}=n,y={open:l.value===0,close:p.close};return j({theirProps:v,ourProps:{},slot:y,slots:o,attrs:c,name:"Disclosure"})}}}),A=E({name:"DisclosureButton",props:{as:{type:[Object,String],default:"button"},disabled:{type:[Boolean],default:!1}},setup(n,{attrs:o,slots:c,expose:m}){let s=V("DisclosureButton"),l=c1(),h=l===null?!1:l===s.panelId,t=z(null);m({el:t,$el:t}),h||X(()=>{s.button.value=t.value});let p=r1(C(()=>({as:n.as,type:o.type})),t);function a(){var g;n.disabled||(h?(s.toggleDisclosure(),(g=$(s.button))==null||g.focus()):s.toggleDisclosure())}function v(g){var S;if(!n.disabled)if(h)switch(g.key){case B.Space:case B.Enter:g.preventDefault(),g.stopPropagation(),s.toggleDisclosure(),(S=$(s.button))==null||S.focus();break}else switch(g.key){case B.Space:case B.Enter:g.preventDefault(),g.stopPropagation(),s.toggleDisclosure();break}}function y(g){switch(g.key){case B.Space:g.preventDefault();break}}return()=>{let g={open:s.disclosureState.value===0},S=h?{ref:t,type:p.value,onClick:a,onKeydown:v}:{id:s.buttonId,ref:t,type:p.value,"aria-expanded":n.disabled?void 0:s.disclosureState.value===0,"aria-controls":$(s.panel)?s.panelId:void 0,disabled:n.disabled?!0:void 0,onClick:a,onKeydown:v,onKeyup:y};return j({ourProps:S,theirProps:n,slot:g,attrs:o,slots:c,name:"DisclosureButton"})}}}),Z=E({name:"DisclosurePanel",props:{as:{type:[Object,String],default:"div"},static:{type:Boolean,default:!1},unmount:{type:Boolean,default:!0}},setup(n,{attrs:o,slots:c,expose:m}){let s=V("DisclosurePanel");m({el:s.panel,$el:s.panel}),G(W,s.panelId);let l=a1(),h=C(()=>l!==null?l.value===O.Open:s.disclosureState.value===0);return()=>{let t={open:s.disclosureState.value===0,close:s.close},p={id:s.panelId,ref:s.panel};return j({ourProps:p,theirProps:n,slot:t,attrs:o,slots:c,features:F.RenderStrategy|F.Static,visible:h.value,name:"DisclosurePanel"})}}});function i1(n,o){return r(),d("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[e("path",{"fill-rule":"evenodd",d:"M10 1c3.866 0 7 1.79 7 4s-3.134 4-7 4-7-1.79-7-4 3.134-4 7-4zm5.694 8.13c.464-.264.91-.583 1.306-.952V10c0 2.21-3.134 4-7 4s-7-1.79-7-4V8.178c.396.37.842.688 1.306.953C5.838 10.006 7.854 10.5 10 10.5s4.162-.494 5.694-1.37zM3 13.179V15c0 2.21 3.134 4 7 4s7-1.79 7-4v-1.822c-.396.37-.842.688-1.306.953-1.532.875-3.548 1.369-5.694 1.369s-4.162-.494-5.694-1.37A7.009 7.009 0 013 13.179z","clip-rule":"evenodd"})])}function d1(n,o){return r(),d("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[e("path",{"fill-rule":"evenodd",d:"M3.25 3A2.25 2.25 0 001 5.25v9.5A2.25 2.25 0 003.25 17h13.5A2.25 2.25 0 0019 14.75v-9.5A2.25 2.25 0 0016.75 3H3.25zm.943 8.752a.75.75 0 01.055-1.06L6.128 9l-1.88-1.693a.75.75 0 111.004-1.114l2.5 2.25a.75.75 0 010 1.114l-2.5 2.25a.75.75 0 01-1.06-.055zM9.75 10.25a.75.75 0 000 1.5h2.5a.75.75 0 000-1.5h-2.5z","clip-rule":"evenodd"})])}function u1(n,o){return r(),d("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[e("path",{"fill-rule":"evenodd",d:"M2.5 4A1.5 1.5 0 001 5.5V6h18v-.5A1.5 1.5 0 0017.5 4h-15zM19 8.5H1v6A1.5 1.5 0 002.5 16h15a1.5 1.5 0 001.5-1.5v-6zM3 13.25a.75.75 0 01.75-.75h1.5a.75.75 0 010 1.5h-1.5a.75.75 0 01-.75-.75zm4.75-.75a.75.75 0 000 1.5h3.5a.75.75 0 000-1.5h-3.5z","clip-rule":"evenodd"})])}function p1(n,o){return r(),d("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[e("path",{d:"M12.232 4.232a2.5 2.5 0 013.536 3.536l-1.225 1.224a.75.75 0 001.061 1.06l1.224-1.224a4 4 0 00-5.656-5.656l-3 3a4 4 0 00.225 5.865.75.75 0 00.977-1.138 2.5 2.5 0 01-.142-3.667l3-3z"}),e("path",{d:"M11.603 7.963a.75.75 0 00-.977 1.138 2.5 2.5 0 01.142 3.667l-3 3a2.5 2.5 0 01-3.536-3.536l1.225-1.224a.75.75 0 00-1.061-1.06l-1.224 1.224a4 4 0 105.656 5.656l3-3a4 4 0 00-.225-5.865z"})])}function h1(n,o){return r(),d("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[e("path",{"fill-rule":"evenodd",d:"M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-5.5-2.5a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0zM10 12a5.99 5.99 0 00-4.793 2.39A6.483 6.483 0 0010 16.5a6.483 6.483 0 004.793-2.11A5.99 5.99 0 0010 12z","clip-rule":"evenodd"})])}function f1(n,o){return r(),d("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[e("path",{d:"M10 9a3 3 0 100-6 3 3 0 000 6zM6 8a2 2 0 11-4 0 2 2 0 014 0zM1.49 15.326a.78.78 0 01-.358-.442 3 3 0 014.308-3.516 6.484 6.484 0 00-1.905 3.959c-.023.222-.014.442.025.654a4.97 4.97 0 01-2.07-.655zM16.44 15.98a4.97 4.97 0 002.07-.654.78.78 0 00.357-.442 3 3 0 00-4.308-3.517 6.484 6.484 0 011.907 3.96 2.32 2.32 0 01-.026.654zM18 8a2 2 0 11-4 0 2 2 0 014 0zM5.304 16.19a.844.844 0 01-.277-.71 5 5 0 019.947 0 .843.843 0 01-.277.71A6.975 6.975 0 0110 18a6.974 6.974 0 01-4.696-1.81z"})])}const g1={class:"relative"},m1={__name:"Dropdown",props:{align:{default:"right"},width:{default:"48"},contentClasses:{default:()=>["py-1","bg-white"]}},setup(n){const o=n,c=h=>{l.value&&h.key==="Escape"&&(l.value=!1)};Y(()=>document.addEventListener("keydown",c)),e1(()=>document.removeEventListener("keydown",c));const m=C(()=>({48:"w-48"})[o.width.toString()]),s=C(()=>o.align==="left"?"origin-top-left left-0":o.align==="right"?"origin-top-right right-0":"origin-top"),l=z(!1);return(h,t)=>(r(),d("div",g1,[e("div",{onClick:t[0]||(t[0]=p=>l.value=!l.value)},[D(h.$slots,"trigger")]),N(e("div",{class:"fixed inset-0 z-40",onClick:t[1]||(t[1]=p=>l.value=!1)},null,512),[[T,l.value]]),f(t1,{"enter-active-class":"transition ease-out duration-200","enter-from-class":"transform opacity-0 scale-95","enter-to-class":"transform opacity-100 scale-100","leave-active-class":"transition ease-in duration-75","leave-from-class":"transform opacity-100 scale-100","leave-to-class":"transform opacity-0 scale-95"},{default:i(()=>[N(e("div",{class:x(["absolute z-50 mt-2 rounded-md shadow-lg",[u(m),u(s)]]),style:{display:"none"},onClick:t[2]||(t[2]=p=>l.value=!1)},[e("div",{class:x(["rounded-md ring-1 ring-black ring-opacity-5",n.contentClasses])},[D(h.$slots,"content")],2)],2),[[T,l.value]])]),_:3})]))}},q={__name:"DropdownLink",setup(n){return(o,c)=>(r(),b(u(k),{class:"block w-full px-4 py-2 text-left text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out"},{default:i(()=>[D(o.$slots,"default")]),_:3}))}},P={__name:"ResponsiveNavLink",props:["href","active"],setup(n){const o=n,c=C(()=>o.active?"block pl-3 pr-4 py-2 border-l-4 border-indigo-400 text-base font-medium text-indigo-700 bg-indigo-50 focus:outline-none focus:text-indigo-800 focus:bg-indigo-100 focus:border-indigo-700 transition duration-150 ease-in-out":"block pl-3 pr-4 py-2 border-l-4 border-transparent text-base font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:text-gray-800 focus:bg-gray-50 focus:border-gray-300 transition duration-150 ease-in-out");return(m,s)=>(r(),b(u(k),{href:n.href,class:x(u(c))},{default:i(()=>[D(m.$slots,"default")]),_:3},8,["href","class"]))}},v1={class:"min-h-screen md:flex bg-gray-100"},y1={class:"hidden md:block flex-none flex-col border-r border-gray-200 pt-5 pb-4 bg-white md:w-1/6 xl:w-2/12"},x1={class:"flex items-center justify-center flex-shrink-0 px-1 object-scale-down"},_1=e("svg",{version:"1.0",xmlns:"http://www.w3.org/2000/svg",class:"h-20 w-44",viewBox:"0 200 600 300",preserveAspectRatio:"xMidYMid meet"},[e("g",{transform:"translate(0,650) scale(0.12,-0.12)",fill:"#000000",stroke:"none"},[e("path",{d:`M295 3436 c-52 -23 -55 -41 -55 -347 0 -297 4 -324 49 -349 36 -19
                        90 -12 116 15 23 22 25 32 25 108 0 108 14 130 84 131 41 1 51 -3 72 -27 21
                        -25 24 -38 24 -111 0 -73 3 -85 23 -104 30 -28 95 -30 128 -3 l24 19 0 315 c0
                        289 -1 317 -18 335 -25 27 -71 35 -112 18 -39 -17 -43 -27 -47 -140 -4 -93
                        -22 -116 -92 -116 -68 0 -86 27 -86 128 0 70 -3 84 -22 106 -29 31 -74 40
                        -113 22z`}),e("path",{d:`M3239 3421 l-29 -29 0 -301 c0 -199 4 -308 11 -324 22 -48 97 -62
                        143 -26 l26 20 0 324 0 324 -26 20 c-39 31 -89 28 -125 -8z`}),e("path",{d:`M4729 3425 c-76 -41 -85 -153 -18 -209 40 -34 86 -41 134 -21 101 41
                        113 170 22 225 -37 23 -101 25 -138 5z m128 -31 c48 -31 46 -119 -4 -163 -35
                        -31 -97 -24 -130 14 -58 67 4 186 87 169 14 -3 35 -12 47 -20z`}),e("path",{d:`M4750 3309 c0 -52 3 -70 13 -67 6 3 13 17 15 32 4 33 22 30 40 -7 7
                        -15 17 -27 22 -27 17 0 19 17 4 46 -10 18 -11 30 -5 32 15 5 14 35 -1 50 -7 7
                        -29 12 -50 12 l-38 0 0 -71z m59 42 c16 -10 6 -31 -15 -31 -8 0 -14 9 -14 20
                        0 22 7 25 29 11z`}),e("path",{d:`M1021 3259 c-114 -22 -178 -102 -188 -232 -10 -129 32 -223 121 -270
                        36 -19 59 -22 203 -25 l162 -4 26 26 25 25 0 210 c0 230 -5 254 -53 271 -35
                        12 -233 12 -296 -1z m116 -179 c55 -20 72 -102 28 -145 -53 -54 -155 -18 -155
                        54 0 77 54 116 127 91z`}),e("path",{d:`M1465 3261 c-49 -21 -50 -27 -53 -325 -2 -155 0 -296 3 -314 9 -49
                        37 -72 88 -72 55 0 82 27 90 90 8 61 41 90 106 90 159 0 251 97 251 265 0 125
                        -41 203 -129 243 -50 23 -71 26 -196 29 -77 2 -149 -1 -160 -6z m254 -182 c38
                        -13 51 -35 51 -85 0 -53 -31 -84 -85 -84 -52 0 -83 22 -91 64 -7 42 12 86 46
                        103 31 16 39 16 79 2z`}),e("path",{d:`M2053 3264 c-51 -12 -53 -25 -53 -361 0 -308 0 -312 22 -333 24 -22
                        86 -27 118 -10 20 11 40 60 40 100 0 44 30 61 122 71 103 12 156 41 195 107
                        26 44 28 55 28 157 0 95 -3 116 -22 152 -25 46 -70 84 -123 103 -35 12 -285
                        23 -327 14z m280 -205 c10 -13 17 -38 17 -65 0 -59 -25 -84 -85 -84 -62 0 -85
                        24 -85 88 0 49 13 71 50 85 28 11 85 -2 103 -24z`}),e("path",{d:`M2623 3256 c-60 -27 -57 -88 11 -234 101 -216 102 -280 5 -319 -64
                        -25 -79 -84 -34 -128 33 -34 77 -32 145 7 104 60 148 117 261 341 83 166 102
                        212 103 250 1 64 -27 92 -91 91 -52 -1 -79 -25 -102 -92 -22 -62 -39 -82 -70
                        -82 -35 0 -57 23 -77 82 -27 80 -86 113 -151 84z`}),e("path",{d:`M3633 3260 c-79 -17 -128 -53 -165 -123 -19 -35 -22 -58 -23 -142 0
                        -86 3 -107 24 -148 29 -59 85 -102 152 -116 27 -6 108 -11 178 -11 115 0 131
                        2 155 21 21 17 26 29 26 68 0 81 -7 85 -170 91 -140 5 -142 5 -166 33 -30 35
                        -31 85 -3 121 17 22 28 26 70 26 44 0 54 -4 96 -42 39 -37 52 -43 88 -43 145
                        0 95 200 -63 253 -48 16 -150 22 -199 12z`}),e("path",{d:`M4237 3260 c-84 -21 -131 -60 -175 -145 -54 -103 -30 -237 59 -326
                        54 -54 113 -69 270 -69 119 0 130 2 153 23 24 21 30 42 27 95 -2 37 -36 62
                        -83 62 l-43 0 50 29 c61 36 78 61 77 119 -2 87 -68 176 -150 200 -56 17 -142
                        22 -185 12z m111 -198 c5 -16 -90 -112 -111 -112 -36 0 -32 88 6 115 26 18 98
                        16 105 -3z`}),e("path",{d:`M1200 2278 c-17 -9 -26 -23 -28 -45 -5 -55 11 -71 79 -79 57 -6 60
                        -8 57 -33 -3 -23 -8 -26 -46 -29 -37 -3 -44 0 -49 17 -3 12 -14 21 -25 21 -15
                        0 -19 -6 -16 -27 4 -37 41 -55 100 -50 63 5 78 20 78 73 0 50 -16 64 -78 64
                        -42 0 -68 20 -58 45 8 21 84 21 92 0 7 -18 44 -20 44 -2 0 7 -6 21 -13 31 -17
                        24 -102 32 -137 14z`}),e("path",{d:`M1390 2171 c0 -114 1 -121 20 -121 19 0 20 7 21 98 l0 97 36 -98 c31
                        -85 39 -98 57 -95 16 2 28 23 56 98 l35 95 3 -97 c2 -90 4 -98 22 -98 19 0 20
                        7 20 121 l0 120 -37 -3 c-38 -3 -38 -3 -67 -86 -16 -45 -31 -81 -34 -78 -2 3
                        -17 40 -32 83 -27 77 -28 78 -64 81 l-36 3 0 -120z`}),e("path",{d:`M1755 2278 c-9 -25 -75 -222 -75 -225 0 -2 11 -3 24 -3 17 0 26 7 30
                        25 6 23 11 25 56 25 45 0 52 -3 60 -25 11 -30 55 -37 46 -7 -4 10 -21 63 -39
                        117 -33 97 -35 100 -65 103 -20 2 -34 -2 -37 -10z m54 -79 c7 -23 15 -47 18
                        -55 4 -11 -5 -14 -37 -14 -29 0 -40 4 -36 13 3 6 11 31 18 55 7 23 15 42 18
                        42 4 0 12 -18 19 -41z`}),e("path",{d:`M1930 2285 c-1 -3 -1 -56 -1 -117 -1 -107 1 -113 20 -116 18 -3 21 2
                        21 37 0 41 0 41 38 41 45 0 62 -14 62 -52 0 -21 5 -28 20 -28 17 0 20 7 20 39
                        0 23 -6 44 -16 52 -14 11 -14 13 0 21 26 15 21 93 -6 112 -21 15 -157 24 -158
                        11z m130 -50 c7 -8 10 -25 6 -40 -6 -22 -12 -25 -51 -25 l-45 0 0 40 c0 40 0
                        40 39 40 22 0 44 -6 51 -15z`}),e("path",{d:`M2130 2270 c0 -16 7 -20 35 -20 l35 0 0 -101 c0 -97 1 -100 21 -97
                        20 3 21 9 23 98 l1 95 33 3 c24 2 32 8 32 23 0 17 -8 19 -90 19 -83 0 -90 -1
                        -90 -20z`}),e("path",{d:`M2440 2170 c0 -113 1 -120 20 -120 17 0 20 7 20 49 l0 50 53 3 c38 2
                        52 7 52 18 0 11 -14 16 -52 18 -51 3 -53 4 -53 33 0 29 1 29 55 29 48 0 55 2
                        55 20 0 18 -7 20 -75 20 l-75 0 0 -120z`}),e("path",{d:`M2620 2171 c0 -114 1 -121 20 -121 17 0 20 7 20 41 l0 40 48 -3 c47
                        -3 47 -3 50 -41 3 -33 6 -38 25 -35 28 4 36 58 12 82 -15 15 -15 17 -1 32 20
                        19 21 74 2 99 -12 16 -29 20 -95 23 l-81 4 0 -121z m135 39 c0 -35 0 -35 -47
                        -38 l-48 -3 0 41 0 41 48 -3 c47 -3 47 -3 47 -38z`}),e("path",{d:`M2883 2280 c-32 -13 -43 -41 -43 -113 0 -92 18 -112 100 -112 84 1
                        100 20 100 115 0 88 -17 111 -86 116 -27 2 -59 0 -71 -6z m105 -107 c3 -69 2
                        -73 -22 -79 -13 -3 -35 -4 -47 -2 -22 3 -24 8 -27 67 -4 87 -1 93 50 89 l43
                        -3 3 -72z`}),e("path",{d:`M3067 2283 c-21 -21 -3 -32 54 -35 l61 -3 -61 -76 c-34 -42 -61 -86
                        -61 -98 0 -20 5 -21 90 -21 83 0 90 2 90 20 0 16 -8 19 -61 22 l-61 3 61 76
                        c34 42 61 86 61 98 0 20 -5 21 -83 21 -46 0 -87 -3 -90 -7z`}),e("path",{d:`M3270 2170 l0 -120 80 0 c73 0 80 2 80 20 0 18 -7 20 -61 20 l-60 0
                        3 33 c3 31 4 32 56 35 37 2 52 7 52 18 0 10 -14 14 -55 14 -54 0 -55 0 -55 30
                        l0 30 60 0 c53 0 60 2 60 20 0 18 -7 20 -80 20 l-80 0 0 -120z`}),e("path",{d:`M3470 2170 c0 -113 1 -120 20 -120 18 0 20 8 22 97 l3 98 47 -98 c46
                        -96 48 -97 83 -97 l35 0 0 120 c0 113 -1 120 -20 120 -19 0 -20 -7 -21 -97 l0
                        -98 -50 98 c-48 95 -50 97 -84 97 l-35 0 0 -120z`}),e("path",{d:`M670 1908 c0 -7 15 -60 33 -118 l33 -105 35 -3 35 -3 32 105 c18 58
                        35 113 39 122 4 12 0 15 -18 12 -21 -3 -28 -16 -53 -95 -27 -84 -46 -117 -46
                        -79 0 8 -11 51 -25 95 -21 65 -30 81 -45 81 -11 0 -20 -6 -20 -12z`}),e("path",{d:`M900 1800 l0 -120 74 0 c54 0 75 4 79 14 8 22 -2 26 -60 26 -52 0
                        -53 1 -53 29 0 29 2 30 52 33 41 2 54 7 56 21 3 14 -5 17 -52 17 -56 0 -56 0
                        -56 30 0 30 1 30 55 30 48 0 55 2 55 20 0 18 -7 20 -75 20 l-75 0 0 -120z`}),e("path",{d:`M1094 1907 c-2 -7 -3 -60 -2 -118 3 -100 4 -104 26 -107 23 -4 23 -3
                        20 92 -2 53 -1 96 2 96 3 0 25 -43 49 -95 l44 -95 38 0 39 0 0 120 c0 113 -1
                        120 -20 120 -18 0 -20 -8 -22 -97 l-3 -98 -50 98 c-48 93 -51 97 -83 97 -18 0
                        -35 -6 -38 -13z`}),e("path",{d:`M1358 1914 c-5 -4 -8 -58 -8 -121 l0 -113 80 0 c109 0 120 11 120
                        118 0 102 -12 115 -113 120 -40 2 -75 0 -79 -4z m140 -46 c7 -7 12 -37 12 -68
                        0 -31 -5 -61 -12 -68 -7 -7 -31 -12 -55 -12 l-43 0 0 80 0 80 43 0 c24 0 48
                        -5 55 -12z`}),e("path",{d:`M1590 1800 c0 -120 0 -121 23 -118 21 3 22 6 22 118 0 112 -1 115
                        -22 118 -23 3 -23 2 -23 -118z`}),e("path",{d:`M1680 1800 c0 -113 1 -120 20 -120 19 0 20 7 21 98 l0 97 50 -97 c48
                        -96 50 -98 84 -98 l35 0 0 121 c0 109 -2 120 -17 117 -15 -3 -19 -18 -23 -96
                        l-5 -93 -47 95 c-47 94 -49 96 -83 96 l-35 0 0 -120z`}),e("path",{d:`M1973 1910 c-32 -13 -43 -41 -43 -115 0 -57 3 -69 25 -90 20 -21 34
                        -25 80 -25 71 0 95 21 95 85 l0 45 -50 0 c-43 0 -50 -3 -50 -20 0 -16 7 -20
                        30 -20 31 0 39 -17 18 -38 -19 -19 -87 -15 -96 6 -13 31 -7 123 9 133 22 14
                        75 11 89 -6 7 -8 21 -15 32 -15 28 0 20 41 -12 57 -29 14 -95 16 -127 3z`}),e("path",{d:`M2335 1913 c-36 -9 -47 -25 -43 -62 3 -25 -1 -40 -15 -54 -23 -23
                        -18 -85 10 -104 21 -16 107 -17 134 -2 15 7 24 7 33 -1 10 -8 15 -6 20 6 4 10
                        0 21 -10 29 -12 8 -15 20 -10 38 5 22 3 27 -13 27 -11 0 -21 -6 -24 -12 -3 -8
                        -20 2 -47 29 -47 45 -49 73 -6 73 16 0 27 -7 31 -20 8 -26 45 -27 45 -1 0 23
                        -25 51 -45 51 -7 0 -19 2 -27 4 -7 2 -22 1 -33 -1z m32 -149 c18 -14 33 -29
                        33 -35 0 -5 -20 -9 -44 -9 -36 0 -45 4 -49 21 -7 25 1 49 16 49 5 0 25 -11 44
                        -26z`}),e("path",{d:`M2640 1912 c-50 -15 -53 -104 -4 -122 14 -6 43 -10 65 -10 37 0 39
                        -2 39 -30 0 -29 -2 -30 -44 -30 -36 0 -46 4 -51 20 -3 11 -15 20 -26 20 -25 0
                        -25 -34 1 -60 15 -15 33 -20 75 -20 64 0 95 22 95 68 0 47 -22 66 -81 72 -49
                        5 -54 7 -54 30 0 22 5 25 38 28 26 2 39 -1 43 -12 9 -23 44 -20 44 3 0 10 -6
                        24 -14 30 -17 14 -94 22 -126 13z`}),e("path",{d:`M2865 1910 c-40 -16 -50 -39 -50 -118 1 -67 3 -77 25 -94 33 -27 135
                        -22 156 7 15 20 20 161 6 182 -18 28 -94 41 -137 23z m93 -42 c16 -16 16 -120
                        0 -136 -18 -18 -85 -15 -92 4 -11 28 -6 120 6 132 7 7 26 12 43 12 17 0 36 -5
                        43 -12z`}),e("path",{d:`M3049 1916 c-2 -2 -3 -56 -1 -120 l3 -116 75 0 c67 0 74 2 74 20 0
                        18 -7 20 -55 20 l-55 0 0 100 c0 90 -2 100 -18 100 -10 0 -21 -2 -23 -4z`}),e("path",{d:`M3224 1905 c-3 -8 -4 -53 -3 -101 4 -76 7 -88 28 -105 23 -19 82 -25
                        128 -13 32 9 44 51 41 145 -3 76 -5 84 -23 84 -18 0 -20 -8 -25 -95 l-5 -95
                        -45 0 -45 0 -3 98 c-2 89 -4 97 -23 97 -11 0 -22 -7 -25 -15z`}),e("path",{d:`M3440 1901 c0 -15 8 -21 33 -23 l32 -3 3 -97 c2 -89 4 -98 22 -98 18
                        0 20 9 22 98 l3 97 33 3 c24 2 32 8 32 23 0 17 -8 19 -90 19 -82 0 -90 -2 -90
                        -19z`}),e("path",{d:`M3649 1916 c-2 -2 -3 -56 -1 -120 3 -107 5 -116 23 -116 18 0 19 8
                        19 120 0 110 -2 120 -18 120 -10 0 -21 -2 -23 -4z`}),e("path",{d:`M3780 1913 c-42 -17 -50 -34 -50 -115 0 -98 15 -118 88 -118 70 0 95
                        17 105 69 10 50 2 120 -16 145 -13 17 -100 31 -127 19z m88 -45 c16 -16 16
                        -120 0 -136 -18 -18 -85 -15 -92 4 -11 28 -6 120 6 132 7 7 26 12 43 12 17 0
                        36 -5 43 -12z`}),e("path",{d:`M3960 1800 c0 -113 1 -120 20 -120 19 0 20 7 21 98 l0 97 50 -97 c48
                        -96 50 -98 84 -98 l35 0 0 120 c0 113 -1 120 -20 120 -19 0 -20 -7 -21 -97 l0
                        -98 -50 98 c-48 95 -50 97 -84 97 l-35 0 0 -120z`})])],-1),w1={class:"mt-5 flex-grow flex flex-col border-t border-gray-200 pt-2"},b1={class:"flex-1 px-2 space-y-1 bg-white","aria-label":"Sidebar"},z1={key:0},M1={class:"flex-1"},k1=e("path",{d:"M6 6L14 10L6 14V6Z",fill:"currentColor"},null,-1),$1=[k1],C1={class:"md:w-5/6 xl:w-10/12"},D1={key:0,class:"bg-white shadow flex justify-between"},B1={class:"max-w-7xl my-auto py-4 px-4 lg:px-8"},S1={class:"bg-white border-b border-gray-100"},L1={class:"max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"},A1={class:"flex justify-between h-16"},P1={class:"hidden md:flex sm:items-center sm:ml-6"},O1={class:"ml-3 relative"},E1={class:"inline-flex rounded-md"},j1={type:"button",class:"inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150"},V1=e("svg",{class:"ml-2 -mr-0.5 h-4 w-4",xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor"},[e("path",{"fill-rule":"evenodd",d:"M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z","clip-rule":"evenodd"})],-1),N1=w(" Account Settings "),T1=w(" Log Out "),H1={class:"my-auto md:hidden"},R1={class:"h-6 w-6",stroke:"currentColor",fill:"none",viewBox:"0 0 24 24"},K1={key:0,class:"py-1 space-y-1"},I1={class:""},F1=e("path",{d:"M6 6L14 10L6 14V6Z",fill:"currentColor"},null,-1),U1=[F1],Z1={class:"pt-4 pb-1 border-t border-gray-300"},q1={class:"px-4"},G1={class:"font-medium text-base text-gray-800"},J1={class:"font-medium text-sm text-gray-500"},Y1={class:"mt-3 space-y-1"},Q1=w(" Account Settings "),W1={class:"mt-3 space-y-1"},X1=w(" Log Out "),e0={class:"bg-gray-100"},r0={__name:"Authenticated",setup(n){const o=[{name:"Vending Machines",icon:d1,current:!1,href:"vends",permission:"read vends"},{name:"Transactions",icon:u1,current:!1,href:"vends-transactions",permission:"read transactions"},{name:"Products",icon:o1,current:!1,href:"products",permission:"read products"},{name:"Product Mapping",icon:p1,current:!1,href:"product-mappings",permission:"read product-mappings"},{name:"Operators",icon:f1,current:!1,href:"operators",permission:"read operators"},{name:"Resource Center",icon:i1,current:!1,href:"resource-centers",permission:"read resource-centers"},{name:"Users",icon:h1,current:!1,href:"users",permission:"read users"}],c=z(!1),m=H().props.value.auth.user.roles,s=H().props.value.auth.user.permissions,l=z([]),h=z([]);return Y(()=>{l.value=m?m.map(t=>t.name):[],h.value=s?s.map(t=>t.name):[]}),(t,p)=>(r(),d("div",null,[e("div",v1,[e("div",y1,[e("div",x1,[f(u(k),{href:"/"},{default:i(()=>[_1]),_:1})]),e("div",w1,[e("nav",b1,[(r(),d(M,null,L(o,a=>(r(),d(M,{key:a.name},[a.children?(r(),b(u(U),{key:1,as:"div",class:"space-y-1"},{default:i(({open:v})=>[f(u(A),{class:x([a.current?"bg-gray-100 text-gray-900":"bg-white text-gray-600 hover:bg-gray-50 hover:text-gray-900","group w-full flex items-center pl-2 pr-1 py-2 text-left text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"])},{default:i(()=>[(r(),b(R(a.icon),{class:"mr-3 flex-shrink-0 h-6 w-6 text-gray-400 group-hover:text-gray-500","aria-hidden":"true"})),e("span",M1,_(a.name),1),(r(),d("svg",{class:x([v?"text-gray-400 rotate-90":"text-gray-300","ml-3 flex-shrink-0 h-5 w-5 transform group-hover:text-gray-400 transition-colors ease-in-out duration-150"]),viewBox:"0 0 20 20","aria-hidden":"true"},$1,2))]),_:2},1032,["class"]),f(u(Z),{class:"space-y-1 py-2 bg-gray-100"},{default:i(()=>[(r(!0),d(M,null,L(a.children,y=>(r(),b(u(k),{key:y.name,as:"a",href:y.href},{default:i(()=>[f(u(A),{class:"group w-full flex items-center pl-3 pr-2 py-2 text-sm font-medium text-gray-600 rounded-md hover:text-gray-900 hover:bg-gray-200"},{default:i(()=>[w(_(y.name),1)]),_:2},1024)]),_:2},1032,["href"]))),128))]),_:2},1024)]),_:2},1024)):(r(),d("div",z1,[f(u(k),{href:t.route(a.href),class:x([t.$page.url==="/"+a.href?"bg-gray-100 text-gray-900":"bg-white text-gray-600 hover:bg-gray-50 hover:text-gray-900","group w-full flex items-center pl-2 py-2 text-sm font-medium rounded-md"])},{default:i(()=>[(r(),b(R(a.icon),{class:x([t.$page.url==="/"+a.href?"text-gray-500":"text-gray-400 group-hover:text-gray-500","mr-3 flex-shrink-0 h-6 w-6"]),"aria-hidden":"true"},null,8,["class"])),w(" "+_(a.name),1)]),_:2},1032,["href","class"])]))],64))),64))])])]),e("div",C1,[t.$slots.header?(r(),d("header",D1,[e("div",B1,[D(t.$slots,"header")]),e("div",null,[e("nav",S1,[e("div",L1,[e("div",A1,[e("div",P1,[e("div",O1,[f(m1,{align:"right",width:"48"},{trigger:i(()=>[e("span",E1,[e("button",j1,[w(_(t.$page.props.auth&&t.$page.props.auth.user?t.$page.props.auth.user.name:null)+" ",1),V1])])]),content:i(()=>[f(q,{href:t.route("self"),method:"get",as:"button"},{default:i(()=>[N1]),_:1},8,["href"]),f(q,{href:t.route("logout"),method:"post",as:"button"},{default:i(()=>[T1]),_:1},8,["href"])]),_:1})])]),e("div",H1,[e("button",{onClick:p[0]||(p[0]=a=>c.value=!c.value),class:"inline-flex items-center justify-center p-3 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out bg-gray-100"},[(r(),d("svg",R1,[e("path",{class:x({hidden:c.value,"inline-flex":!c.value}),"stroke-linecap":"round","stroke-linejoin":"round","stroke-width":"2",d:"M4 6h16M4 12h16M4 18h16"},null,2),e("path",{class:x({hidden:!c.value,"inline-flex":c.value}),"stroke-linecap":"round","stroke-linejoin":"round","stroke-width":"2",d:"M6 18L18 6M6 6l12 12"},null,2)]))])])])])])])])):n1("",!0),e("div",{class:x([{block:c.value,hidden:!c.value},"md:hidden bg-gray-50"])},[(r(),d(M,null,L(o,a=>(r(),d(M,{key:a.name},[a.children?(r(),b(u(U),{key:1,as:"div",class:"space-y-1"},{default:i(({open:v})=>[f(u(A),{class:"pt-2 pb-2 mb-1 pl-4 space-y-1 flex"},{default:i(()=>[e("span",I1,_(a.name),1),(r(),d("svg",{class:x([v?"text-gray-400 rotate-90":"text-gray-300","ml-3 flex-shrink-0 h-5 w-5 transform group-hover:text-gray-400 transition-colors ease-in-out duration-150"]),viewBox:"0 0 20 20","aria-hidden":"true"},U1,2))]),_:2},1024),f(u(Z),{class:"py-1 space-y-1"},{default:i(()=>[(r(!0),d(M,null,L(a.children,y=>(r(),b(u(k),{key:y.name,as:"a",href:y.href},{default:i(()=>[f(u(A),{class:"group w-full flex items-center pl-11 pr-2 py-3 text-sm font-medium text-gray-600 rounded-md hover:text-gray-900 hover:bg-gray-50"},{default:i(()=>[w(_(y.name),1)]),_:2},1024)]),_:2},1032,["href"]))),128))]),_:2},1024)]),_:2},1024)):(r(),d("div",K1,[f(P,{href:t.route(a.href),active:t.route().current(a.href)},{default:i(()=>[w(_(a.name),1)]),_:2},1032,["href","active"])]))],64))),64)),e("div",Z1,[e("div",q1,[e("div",G1,_(t.$page.props.auth&&t.$page.props.auth.user?t.$page.props.auth.user.name:null),1),e("div",J1,_(t.$page.props.auth&&t.$page.props.auth.user?t.$page.props.auth.user.email:null),1)]),e("div",Y1,[f(P,{href:t.route("self"),method:"get",as:"button"},{default:i(()=>[Q1]),_:1},8,["href"])]),e("div",W1,[f(P,{href:t.route("logout"),method:"post",as:"button"},{default:i(()=>[X1]),_:1},8,["href"])])])],2),e("main",e0,[D(t.$slots,"default")])])])]))}};export{r0 as _,p1 as r};

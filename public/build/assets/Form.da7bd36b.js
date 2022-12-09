import{x as J,i as I,k as R,y as q,S as h,C as ve,z as ne,F as ue,j as le,A as se,B as me,G as F,D as be,o as T,g as U,d,c as Y,w as S,a as y,r as fe,b as E,p as M,m as _e,t as K,u as ee,T as xe,e as ye,f as D}from"./app.7e392996.js";import{_ as re}from"./Button.a4bc7dc6.js";import{_ as B}from"./FormInput.c9fc7f3a.js";import{m as ge,y as he,f as Se,a as Oe,O as Ve,_ as Ce}from"./Modal.7bb3d2e4.js";import{_ as te}from"./MultiSelect.ab19e96f.js";import{u as j,c as Re,l as oe,P as Ie,V as H,w as ae,t as W,o as N,R as Q,p as we,a as $}from"./open-closed.dc611207.js";import{b as ke}from"./use-resolve-button-type.3a66aace.js";import{r as Pe}from"./ArrowUturnLeftIcon.52ddfe23.js";import{r as $e}from"./CheckCircleIcon.e8e6b0bc.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.1e831869.js";function Ne(e){throw new Error("Unexpected object: "+e)}var P=(e=>(e[e.First=0]="First",e[e.Previous=1]="Previous",e[e.Next=2]="Next",e[e.Last=3]="Last",e[e.Specific=4]="Specific",e[e.Nothing=5]="Nothing",e))(P||{});function Te(e,r){let s=r.resolveItems();if(s.length<=0)return null;let o=r.resolveActiveIndex(),t=o!=null?o:-1,l=(()=>{switch(e.focus){case 0:return s.findIndex(i=>!r.resolveDisabled(i));case 1:{let i=s.slice().reverse().findIndex((v,u,n)=>t!==-1&&n.length-u-1>=t?!1:!r.resolveDisabled(v));return i===-1?i:s.length-1-i}case 2:return s.findIndex((i,v)=>v<=t?!1:!r.resolveDisabled(i));case 3:{let i=s.slice().reverse().findIndex(v=>!r.resolveDisabled(v));return i===-1?i:s.length-1-i}case 4:return s.findIndex(i=>r.resolveId(i)===e.id);case 5:return null;default:Ne(e)}})();return l===-1?o:l}function De({container:e,accept:r,walk:s,enabled:o}){J(()=>{let t=e.value;if(!t||o!==void 0&&!o.value)return;let l=ge(e);if(!l)return;let i=Object.assign(u=>r(u),{acceptNode:r}),v=l.createTreeWalker(t,NodeFilter.SHOW_ELEMENT,i,!1);for(;v.nextNode();)s(v.currentNode)})}function ie(e={},r=null,s=[]){for(let[o,t]of Object.entries(e))ce(s,de(r,o),t);return s}function de(e,r){return e?e+"["+r+"]":r}function ce(e,r,s){if(Array.isArray(s))for(let[o,t]of s.entries())ce(e,de(r,o.toString()),t);else s instanceof Date?e.push([r,s.toISOString()]):typeof s=="boolean"?e.push([r,s?"1":"0"]):typeof s=="string"?e.push([r,s]):typeof s=="number"?e.push([r,`${s}`]):s==null?e.push([r,""]):ie(s,r,e)}function Be(e,r,s){let o=I(s==null?void 0:s.value),t=R(()=>e.value!==void 0);return[R(()=>t.value?e.value:o.value),function(l){return t.value||(o.value=l),r==null?void 0:r(l)}]}function Ae(e,r){return e===r}var Ee=(e=>(e[e.Open=0]="Open",e[e.Closed=1]="Closed",e))(Ee||{}),Ue=(e=>(e[e.Single=0]="Single",e[e.Multi=1]="Multi",e))(Ue||{}),je=(e=>(e[e.Pointer=0]="Pointer",e[e.Other=1]="Other",e))(je||{});let pe=Symbol("ComboboxContext");function z(e){let r=be(pe,null);if(r===null){let s=new Error(`<${e} /> is missing a parent <Combobox /> component.`);throw Error.captureStackTrace&&Error.captureStackTrace(s,z),s}return r}let Le=q({name:"Combobox",emits:{"update:modelValue":e=>!0},props:{as:{type:[Object,String],default:"template"},disabled:{type:[Boolean],default:!1},by:{type:[String,Function],default:()=>Ae},modelValue:{type:[Object,String,Number,Boolean],default:void 0},defaultValue:{type:[Object,String,Number,Boolean],default:void 0},name:{type:String},nullable:{type:Boolean,default:!1},multiple:{type:[Boolean],default:!1}},inheritAttrs:!1,setup(e,{slots:r,attrs:s,emit:o}){let t=I(1),l=I(null),i=I(null),v=I(null),u=I(null),n=I({static:!1,hold:!1}),a=I([]),V=I(null),c=I(1),O=I(!1);function x(m=f=>f){let f=V.value!==null?a.value[V.value]:null,b=Ve(m(a.value.slice()),C=>N(C.dataRef.domRef)),p=f?b.indexOf(f):null;return p===-1&&(p=null),{options:b,activeOptionIndex:p}}let w=R(()=>e.multiple?1:0),A=R(()=>e.nullable),[L,G]=Be(R(()=>e.modelValue),m=>o("update:modelValue",m),R(()=>e.defaultValue)),g={comboboxState:t,value:L,mode:w,compare(m,f){if(typeof e.by=="string"){let b=e.by;return(m==null?void 0:m[b])===(f==null?void 0:f[b])}return e.by(m,f)},nullable:A,inputRef:i,labelRef:l,buttonRef:v,optionsRef:u,disabled:R(()=>e.disabled),options:a,change(m){G(m)},activeOptionIndex:R(()=>{if(O.value&&V.value===null&&a.value.length>0){let m=a.value.findIndex(f=>!f.dataRef.disabled);if(m!==-1)return m}return V.value}),activationTrigger:c,optionsPropsRef:n,closeCombobox(){O.value=!1,!e.disabled&&t.value!==1&&(t.value=1,V.value=null)},openCombobox(){if(O.value=!0,e.disabled||t.value===0)return;let m=a.value.findIndex(f=>{let b=h(f.dataRef.value);return j(w.value,{[0]:()=>g.compare(h(g.value.value),h(b)),[1]:()=>h(g.value.value).some(p=>g.compare(h(p),h(b)))})});m!==-1&&(V.value=m),t.value=0},goToOption(m,f,b){if(O.value=!1,e.disabled||u.value&&!n.value.static&&t.value===1)return;let p=x();if(p.activeOptionIndex===null){let k=p.options.findIndex(Z=>!Z.dataRef.disabled);k!==-1&&(p.activeOptionIndex=k)}let C=Te(m===P.Specific?{focus:P.Specific,id:f}:{focus:m},{resolveItems:()=>p.options,resolveActiveIndex:()=>p.activeOptionIndex,resolveId:k=>k.id,resolveDisabled:k=>k.dataRef.disabled});V.value=C,c.value=b!=null?b:1,a.value=p.options},selectOption(m){let f=a.value.find(p=>p.id===m);if(!f)return;let{dataRef:b}=f;G(j(w.value,{[0]:()=>b.value,[1]:()=>{let p=h(g.value.value).slice(),C=h(b.value),k=p.findIndex(Z=>g.compare(C,h(Z)));return k===-1?p.push(C):p.splice(k,1),p}}))},selectActiveOption(){if(g.activeOptionIndex.value===null)return;let{dataRef:m,id:f}=a.value[g.activeOptionIndex.value];G(j(w.value,{[0]:()=>m.value,[1]:()=>{let b=h(g.value.value).slice(),p=h(m.value),C=b.findIndex(k=>g.compare(p,h(k)));return C===-1?b.push(p):b.splice(C,1),b}})),g.goToOption(P.Specific,f)},registerOption(m,f){let b={id:m,dataRef:f},p=x(C=>[...C,b]);if(V.value===null){let C=f.value.value;j(w.value,{[0]:()=>g.compare(h(g.value.value),h(C)),[1]:()=>h(g.value.value).some(k=>g.compare(h(k),h(C)))})&&(p.activeOptionIndex=p.options.indexOf(b))}a.value=p.options,V.value=p.activeOptionIndex,c.value=1},unregisterOption(m){let f=x(b=>{let p=b.findIndex(C=>C.id===m);return p!==-1&&b.splice(p,1),b});a.value=f.options,V.value=f.activeOptionIndex,c.value=1}};he([i,v,u],()=>g.closeCombobox(),R(()=>t.value===0)),ve(pe,g),Re(R(()=>j(t.value,{[0]:oe.Open,[1]:oe.Closed})));let X=R(()=>g.activeOptionIndex.value===null?null:a.value[g.activeOptionIndex.value].dataRef.value);return()=>{let{name:m,disabled:f,...b}=e,p={open:t.value===0,disabled:f,activeIndex:g.activeOptionIndex.value,activeOption:X.value,value:L.value};return ne(ue,[...m!=null&&L.value!=null?ie({[m]:L.value}).map(([C,k])=>ne(Se,Ie({features:Oe.Hidden,key:C,as:"input",type:"hidden",hidden:!0,readOnly:!0,name:C,value:k}))):[],H({theirProps:{...s,...ae(b,["modelValue","defaultValue","nullable","multiple","onUpdate:modelValue","by"])},ourProps:{},slot:p,slots:r,attrs:s,name:"Combobox"})])}}}),Fe=q({name:"ComboboxLabel",props:{as:{type:[Object,String],default:"label"}},setup(e,{attrs:r,slots:s}){let o=z("ComboboxLabel"),t=`headlessui-combobox-label-${W()}`;function l(){var i;(i=N(o.inputRef))==null||i.focus({preventScroll:!0})}return()=>{let i={open:o.comboboxState.value===0,disabled:o.disabled.value},v={id:t,ref:o.labelRef,onClick:l};return H({ourProps:v,theirProps:e,slot:i,attrs:r,slots:s,name:"ComboboxLabel"})}}}),Me=q({name:"ComboboxButton",props:{as:{type:[Object,String],default:"button"}},setup(e,{attrs:r,slots:s,expose:o}){let t=z("ComboboxButton"),l=`headlessui-combobox-button-${W()}`;o({el:t.buttonRef,$el:t.buttonRef});function i(n){t.disabled.value||(t.comboboxState.value===0?t.closeCombobox():(n.preventDefault(),t.openCombobox()),F(()=>{var a;return(a=N(t.inputRef))==null?void 0:a.focus({preventScroll:!0})}))}function v(n){switch(n.key){case $.ArrowDown:n.preventDefault(),n.stopPropagation(),t.comboboxState.value===1&&t.openCombobox(),F(()=>{var a;return(a=t.inputRef.value)==null?void 0:a.focus({preventScroll:!0})});return;case $.ArrowUp:n.preventDefault(),n.stopPropagation(),t.comboboxState.value===1&&(t.openCombobox(),F(()=>{t.value.value||t.goToOption(P.Last)})),F(()=>{var a;return(a=t.inputRef.value)==null?void 0:a.focus({preventScroll:!0})});return;case $.Escape:if(t.comboboxState.value!==0)return;n.preventDefault(),t.optionsRef.value&&!t.optionsPropsRef.value.static&&n.stopPropagation(),t.closeCombobox(),F(()=>{var a;return(a=t.inputRef.value)==null?void 0:a.focus({preventScroll:!0})});return}}let u=ke(R(()=>({as:e.as,type:r.type})),t.buttonRef);return()=>{var n,a;let V={open:t.comboboxState.value===0,disabled:t.disabled.value,value:t.value.value},c={ref:t.buttonRef,id:l,type:u.value,tabindex:"-1","aria-haspopup":!0,"aria-controls":(n=N(t.optionsRef))==null?void 0:n.id,"aria-expanded":t.disabled.value?void 0:t.comboboxState.value===0,"aria-labelledby":t.labelRef.value?[(a=N(t.labelRef))==null?void 0:a.id,l].join(" "):void 0,disabled:t.disabled.value===!0?!0:void 0,onKeydown:v,onClick:i};return H({ourProps:c,theirProps:e,slot:V,attrs:r,slots:s,name:"ComboboxButton"})}}}),qe=q({name:"ComboboxInput",props:{as:{type:[Object,String],default:"input"},static:{type:Boolean,default:!1},unmount:{type:Boolean,default:!0},displayValue:{type:Function}},emits:{change:e=>!0},setup(e,{emit:r,attrs:s,slots:o,expose:t}){let l=z("ComboboxInput"),i=`headlessui-combobox-input-${W()}`;t({el:l.inputRef,$el:l.inputRef});let v=I(l.value.value),u=()=>{var c;let O=l.value.value;return N(l.inputRef)?typeof e.displayValue<"u"?(c=e.displayValue(O))!=null?c:"":typeof O=="string"?O:"":""};le(()=>{se([l.value],()=>v.value=u(),{flush:"sync",immediate:!0}),se([v,l.comboboxState],([c,O],[x,w])=>{let A=N(l.inputRef);!A||(w===0&&O===1||c!==x)&&(A.value=c)},{immediate:!0})});function n(c){switch(c.key){case $.Backspace:case $.Delete:if(l.mode.value!==0||!l.nullable.value)return;let O=c.currentTarget;requestAnimationFrame(()=>{if(O.value===""){l.change(null);let x=N(l.optionsRef);x&&(x.scrollTop=0),l.goToOption(P.Nothing)}});break;case $.Enter:if(l.comboboxState.value!==0||c.isComposing)return;if(c.preventDefault(),c.stopPropagation(),l.activeOptionIndex.value===null){l.closeCombobox();return}l.selectActiveOption(),l.mode.value===0&&l.closeCombobox();break;case $.ArrowDown:return c.preventDefault(),c.stopPropagation(),j(l.comboboxState.value,{[0]:()=>l.goToOption(P.Next),[1]:()=>l.openCombobox()});case $.ArrowUp:return c.preventDefault(),c.stopPropagation(),j(l.comboboxState.value,{[0]:()=>l.goToOption(P.Previous),[1]:()=>{l.openCombobox(),F(()=>{l.value.value||l.goToOption(P.Last)})}});case $.Home:case $.PageUp:return c.preventDefault(),c.stopPropagation(),l.goToOption(P.First);case $.End:case $.PageDown:return c.preventDefault(),c.stopPropagation(),l.goToOption(P.Last);case $.Escape:if(l.comboboxState.value!==0)return;c.preventDefault(),l.optionsRef.value&&!l.optionsPropsRef.value.static&&c.stopPropagation(),l.closeCombobox();break;case $.Tab:if(l.comboboxState.value!==0)return;l.mode.value===0&&l.selectActiveOption(),l.closeCombobox();break}}function a(c){r("change",c)}function V(c){l.openCombobox(),r("change",c)}return()=>{var c,O,x,w,A,L;let G={open:l.comboboxState.value===0},g={"aria-controls":(c=l.optionsRef.value)==null?void 0:c.id,"aria-expanded":l.disabled.value?void 0:l.comboboxState.value===0,"aria-activedescendant":l.activeOptionIndex.value===null||(O=l.options.value[l.activeOptionIndex.value])==null?void 0:O.id,"aria-multiselectable":l.mode.value===1?!0:void 0,"aria-labelledby":(A=(x=N(l.labelRef))==null?void 0:x.id)!=null?A:(w=N(l.buttonRef))==null?void 0:w.id,id:i,onKeydown:n,onChange:a,onInput:V,role:"combobox",type:(L=s.type)!=null?L:"text",tabIndex:0,ref:l.inputRef},X=ae(e,["displayValue"]);return H({ourProps:g,theirProps:X,slot:G,attrs:s,slots:o,features:Q.RenderStrategy|Q.Static,name:"ComboboxInput"})}}}),He=q({name:"ComboboxOptions",props:{as:{type:[Object,String],default:"ul"},static:{type:Boolean,default:!1},unmount:{type:Boolean,default:!0},hold:{type:[Boolean],default:!1}},setup(e,{attrs:r,slots:s,expose:o}){let t=z("ComboboxOptions"),l=`headlessui-combobox-options-${W()}`;o({el:t.optionsRef,$el:t.optionsRef}),J(()=>{t.optionsPropsRef.value.static=e.static}),J(()=>{t.optionsPropsRef.value.hold=e.hold});let i=we(),v=R(()=>i!==null?i.value===oe.Open:t.comboboxState.value===0);return De({container:R(()=>N(t.optionsRef)),enabled:R(()=>t.comboboxState.value===0),accept(u){return u.getAttribute("role")==="option"?NodeFilter.FILTER_REJECT:u.hasAttribute("role")?NodeFilter.FILTER_SKIP:NodeFilter.FILTER_ACCEPT},walk(u){u.setAttribute("role","none")}}),()=>{var u,n,a,V;let c={open:t.comboboxState.value===0},O={"aria-activedescendant":t.activeOptionIndex.value===null||(u=t.options.value[t.activeOptionIndex.value])==null?void 0:u.id,"aria-labelledby":(V=(n=N(t.labelRef))==null?void 0:n.id)!=null?V:(a=N(t.buttonRef))==null?void 0:a.id,id:l,ref:t.optionsRef,role:"listbox"},x=ae(e,["hold"]);return H({ourProps:O,theirProps:x,slot:c,attrs:r,slots:s,features:Q.RenderStrategy|Q.Static,visible:v.value,name:"ComboboxOptions"})}}}),ze=q({name:"ComboboxOption",props:{as:{type:[Object,String],default:"li"},value:{type:[Object,String,Number,Boolean]},disabled:{type:Boolean,default:!1}},setup(e,{slots:r,attrs:s,expose:o}){let t=z("ComboboxOption"),l=`headlessui-combobox-option-${W()}`,i=I(null);o({el:i,$el:i});let v=R(()=>t.activeOptionIndex.value!==null?t.options.value[t.activeOptionIndex.value].id===l:!1),u=R(()=>j(t.mode.value,{[0]:()=>t.compare(h(t.value.value),h(e.value)),[1]:()=>h(t.value.value).some(x=>t.compare(h(x),h(e.value)))})),n=R(()=>({disabled:e.disabled,value:e.value,domRef:i}));le(()=>t.registerOption(l,n)),me(()=>t.unregisterOption(l)),J(()=>{t.comboboxState.value===0&&(!v.value||t.activationTrigger.value!==0&&F(()=>{var x,w;return(w=(x=N(i))==null?void 0:x.scrollIntoView)==null?void 0:w.call(x,{block:"nearest"})}))});function a(x){if(e.disabled)return x.preventDefault();t.selectOption(l),t.mode.value===0&&t.closeCombobox()}function V(){if(e.disabled)return t.goToOption(P.Nothing);t.goToOption(P.Specific,l)}function c(){e.disabled||v.value||t.goToOption(P.Specific,l,0)}function O(){e.disabled||!v.value||t.optionsPropsRef.value.hold||t.goToOption(P.Nothing)}return()=>{let{disabled:x}=e,w={active:v.value,selected:u.value,disabled:x},A={id:l,ref:i,role:"option",tabIndex:x===!0?void 0:-1,"aria-disabled":x===!0?!0:void 0,"aria-selected":u.value,disabled:void 0,onClick:a,onFocus:V,onPointermove:c,onMousemove:c,onPointerleave:O,onMouseleave:O};return H({ourProps:A,theirProps:e,slot:w,attrs:s,slots:r,name:"ComboboxOption"})}}});function Ge(e,r){return T(),U("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[d("path",{d:"M6.5 9a2.5 2.5 0 115 0 2.5 2.5 0 01-5 0z"}),d("path",{"fill-rule":"evenodd",d:"M10 18a8 8 0 100-16 8 8 0 000 16zM9 5a4 4 0 102.248 7.309l1.472 1.471a.75.75 0 101.06-1.06l-1.471-1.472A4 4 0 009 5z","clip-rule":"evenodd"})])}const Ke={class:"flex space-x-1"},We={key:0,class:"text-red-500"},Ye={class:"relative mt-1"},Je=["onClick"],Qe={class:"block truncate"},Xe={__name:"SearchAddressInput",props:{modelValue:String,required:[Boolean,String]},emits:["update:modelValue","selected"],setup(e,{emit:r}){const s=I([]),o=_.debounce(async i=>{const v="https://developers.onemap.sg/commonapi/search?searchVal="+i.target.value+"&returnGeom=Y&getAddrDetails=Y";let u=await(await fetch(v)).json();u&&(s.value=await u.results)},300);function t(i){r("update:modelValue",i.target.value),o(i)}function l(i){r("selected",i)}return(i,v)=>(T(),Y(E(Le),{as:"div"},{default:S(()=>[d("div",Ke,[y(E(Fe),{class:"block text-sm font-medium text-gray-700"},{default:S(()=>[fe(i.$slots,"default")]),_:3}),e.required?(T(),U("span",We," * ")):M("",!0)]),d("div",Ye,[y(E(qe),{class:"w-full rounded-md border border-gray-300 bg-white py-2 pl-3 pr-10 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm",onInput:t,value:e.modelValue},null,8,["value"]),y(E(Me),{class:"absolute inset-y-0 right-0 flex items-center rounded-r-md px-2 focus:outline-none"},{default:S(()=>[y(E(Ge),{class:"h-5 w-5 text-gray-400","aria-hidden":"true"})]),_:1}),s.value.length>0?(T(),Y(E(He),{key:0,class:"absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm"},{default:S(()=>[(T(!0),U(ue,null,_e(s.value,u=>(T(),Y(E(ze),{as:"template"},{default:S(()=>[d("li",{class:"relative cursor-default select-none py-2 pl-3 pr-9 hover:bg-gray-100",onClick:n=>l(u)},[d("span",Qe,K(u.ADDRESS),1)],8,Je)]),_:2},1024))),256))]),_:1})):M("",!0)])]),_:3}))}},Ze={class:"flex flex-col md:flex-row space-x-2"},et={key:0,class:"text-gray-600"},tt={key:1},ot={key:2,class:"text-gray-600"},lt=["onSubmit"],at={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},nt={class:"sm:col-span-4"},st=D(" Name "),rt={class:"sm:col-span-2"},ut=D(" Alias "),it={class:"sm:col-span-3"},dt=D(" UEN "),ct={class:"sm:col-span-3"},pt=d("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Base Currency ",-1),vt={key:0,class:"text-sm text-red-600"},mt=d("div",{class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},[d("div",{class:"relative"},[d("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[d("div",{class:"w-full border-t border-gray-300"})]),d("div",{class:"relative flex justify-center"},[d("span",{class:"px-4 bg-white text-lg font-medium text-gray-900"}," Contact ")])])],-1),bt={class:"sm:col-span-3"},ft=D(" Name "),_t={class:"sm:col-span-3"},xt=D(" Email "),yt={class:"sm:col-span-2"},gt=d("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Phone Code ",-1),ht={key:0,class:"text-sm text-red-600"},St={class:"sm:col-span-4"},Ot=D(" Phone Number "),Vt=d("div",{class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},[d("div",{class:"relative"},[d("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[d("div",{class:"w-full border-t border-gray-300"})]),d("div",{class:"relative flex justify-center"},[d("span",{class:"px-3 bg-white text-lg font-medium text-gray-900"}," Address ")])])],-1),Ct={class:"sm:col-span-6"},Rt=D(" Postcode "),It={class:"sm:col-span-3"},wt=D(" Unit Num "),kt={class:"sm:col-span-3"},Pt=D(" Block Num "),$t={class:"sm:col-span-3"},Nt=D(" Building Name "),Tt={class:"sm:col-span-3"},Dt=D(" Street Name "),Bt={class:"sm:col-span-3"},At=d("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Country ",-1),Et={key:0,class:"text-sm text-red-600"},Ut={class:"sm:col-span-3 hidden"},jt=D(" Latitude "),Lt={class:"sm:col-span-3 hidden"},Ft=D(" Longitude "),Mt={class:"sm:col-span-6"},qt={class:"flex space-x-1 mt-5 justify-end"},Ht=d("span",null," Back ",-1),zt=d("span",null," Save ",-1),oo={__name:"Form",props:{profile:Object,countries:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(e,{emit:r}){const s=e,o=I(ee(l())),t=I([]);le(()=>{t.value=s.countries.data,o.value=s.profile?ee(s.profile):ee(l()),s.type==="create"&&(o.value.base_currency_id=o.value.base_currency_id?o.value.base_currency_id:t.value[0],o.value.address.country_id=o.value.address.country_id?o.value.address.country_id:t.value[0],o.value.contact.phone_country_id=o.value.contact.phone_country_id?o.value.contact.phone_country_id:t.value[0])});function l(){return{alias:"",name:"",uen:"",base_currency_id:"",address:{block_num:"",building:"",country_id:"",latitude:"",longitude:"",postcode:"",street_name:"",unit_num:""},contact:{name:"",email:"",phone_country_id:"",phone_num:""}}}function i(u){o.value.address={block_num:u.BLK_NO,building:u.BUILDING,country_id:t.value[0],latitude:u.LATITUDE,longitude:u.LONGTITUDE,postcode:u.POSTAL,street_name:u.ROAD_NAME,unit_num:""}}function v(){o.value.clearErrors(),s.type==="create"&&o.value.transform(u=>({...u,base_currency_id:u.base_currency_id.id,address:{...u.address,country_id:u.address.country_id.id},contact:{...u.contact,phone_country_id:u.contact.phone_country_id.id}})).post("/profiles/create",{onSuccess:()=>{r("modalClose")},preserveState:!0,replace:!0}),s.type==="update"&&o.value.transform(u=>({...u,base_currency_id:u.base_currency_id.id,address:{...u.address,country_id:u.address.country_id.id},contact:{...u.contact,phone_country_id:u.contact.phone_country_id.id}})).post("/profiles/"+o.value.id+"/update",{onSuccess:()=>{r("modalClose")},preserveState:!0,replace:!0})}return(u,n)=>(T(),Y(xe,{to:"body"},[y(Ce,{open:e.showModal,onModalClose:n[17]||(n[17]=a=>u.$emit("modalClose"))},{header:S(()=>[d("div",Ze,[s.profile?(T(),U("span",et," Editing ")):M("",!0),s.profile?(T(),U("span",tt,K(s.profile.name),1)):(T(),U("span",ot," Create New Profile "))])]),default:S(()=>[d("form",{onSubmit:ye(v,["prevent"]),id:"submit"},[d("div",at,[d("div",nt,[y(B,{modelValue:o.value.name,"onUpdate:modelValue":n[0]||(n[0]=a=>o.value.name=a),error:o.value.errors.name,required:"true"},{default:S(()=>[st]),_:1},8,["modelValue","error"])]),d("div",rt,[y(B,{modelValue:o.value.alias,"onUpdate:modelValue":n[1]||(n[1]=a=>o.value.alias=a),error:o.value.errors.alias},{default:S(()=>[ut]),_:1},8,["modelValue","error"])]),d("div",it,[y(B,{modelValue:o.value.uen,"onUpdate:modelValue":n[2]||(n[2]=a=>o.value.uen=a),error:o.value.errors.uen},{default:S(()=>[dt]),_:1},8,["modelValue","error"])]),d("div",ct,[pt,y(te,{modelValue:o.value.base_currency_id,"onUpdate:modelValue":n[3]||(n[3]=a=>o.value.base_currency_id=a),options:t.value,trackBy:"id",valueProp:"id",label:"currency_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),o.value.errors.base_currency_id?(T(),U("div",vt,K(o.value.errors.base_currency_id),1)):M("",!0)]),mt,d("div",bt,[y(B,{modelValue:o.value.contact.name,"onUpdate:modelValue":n[4]||(n[4]=a=>o.value.contact.name=a),error:o.value.errors["contact.name"]},{default:S(()=>[ft]),_:1},8,["modelValue","error"])]),d("div",_t,[y(B,{modelValue:o.value.contact.email,"onUpdate:modelValue":n[5]||(n[5]=a=>o.value.contact.email=a),error:o.value.errors["contact.email"]},{default:S(()=>[xt]),_:1},8,["modelValue","error"])]),d("div",yt,[gt,y(te,{modelValue:o.value.contact.phone_country_id,"onUpdate:modelValue":n[6]||(n[6]=a=>o.value.contact.phone_country_id=a),options:t.value,trackBy:"id",valueProp:"id",label:"phone_code",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),o.value.errors["contact.phone_country_id"]?(T(),U("div",ht,K(o.value.errors["contact.phone_country_id"]),1)):M("",!0)]),d("div",St,[y(B,{modelValue:o.value.contact.phone_num,"onUpdate:modelValue":n[7]||(n[7]=a=>o.value.contact.phone_num=a),required:"true",error:o.value.errors["contact.phone_num"]},{default:S(()=>[Ot]),_:1},8,["modelValue","error"])]),Vt,d("div",Ct,[y(Xe,{modelValue:o.value.address.postcode,"onUpdate:modelValue":n[8]||(n[8]=a=>o.value.address.postcode=a),onSelected:i,required:"true",error:o.value.errors["address.postcode"]},{default:S(()=>[Rt]),_:1},8,["modelValue","error"])]),d("div",It,[y(B,{modelValue:o.value.address.unit_num,"onUpdate:modelValue":n[9]||(n[9]=a=>o.value.address.unit_num=a),required:"true",error:o.value.errors["address.unit_num"]},{default:S(()=>[wt]),_:1},8,["modelValue","error"])]),d("div",kt,[y(B,{modelValue:o.value.address.block_num,"onUpdate:modelValue":n[10]||(n[10]=a=>o.value.address.block_num=a),error:o.value.errors["address.block_num"]},{default:S(()=>[Pt]),_:1},8,["modelValue","error"])]),d("div",$t,[y(B,{modelValue:o.value.address.building,"onUpdate:modelValue":n[11]||(n[11]=a=>o.value.address.building=a),error:o.value.errors["address.building"]},{default:S(()=>[Nt]),_:1},8,["modelValue","error"])]),d("div",Tt,[y(B,{modelValue:o.value.address.street_name,"onUpdate:modelValue":n[12]||(n[12]=a=>o.value.address.street_name=a),required:"true",error:o.value.errors["address.street_name"]},{default:S(()=>[Dt]),_:1},8,["modelValue","error"])]),d("div",Bt,[At,y(te,{modelValue:o.value.address.country_id,"onUpdate:modelValue":n[13]||(n[13]=a=>o.value.address.country_id=a),options:t.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),o.value.errors["address.country_id"]?(T(),U("div",Et,K(o.value.errors["address.country_id"]),1)):M("",!0)]),d("div",Ut,[y(B,{modelValue:o.value.address.latitude,"onUpdate:modelValue":n[14]||(n[14]=a=>o.value.address.latitude=a)},{default:S(()=>[jt]),_:1},8,["modelValue"])]),d("div",Lt,[y(B,{modelValue:o.value.address.longitude,"onUpdate:modelValue":n[15]||(n[15]=a=>o.value.address.longitude=a)},{default:S(()=>[Ft]),_:1},8,["modelValue"])])]),d("div",Mt,[d("div",qt,[y(re,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:n[16]||(n[16]=a=>u.$emit("modalClose")),form:"submit"},{default:S(()=>[y(E(Pe),{class:"w-4 h-4"}),Ht]),_:1}),y(re,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:S(()=>[y(E($e),{class:"w-4 h-4"}),zt]),_:1})])])],40,lt)]),_:1},8,["open"])]))}};export{oo as default};

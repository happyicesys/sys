import{s as Z,g as w,j as O,x as G,a9 as S,B as fe,h as oe,A as ne,z as ue,F as ie,y as be,G as K,C as xe,o as A,f as F,b as c,c as Q,w as V,a as h,u as j,r as _e,l as z,k as ye,t as J,T as le,q as ge,d as E,e as he}from"./app.2df6fe58.js";import{_ as re}from"./Button.47a00e17.js";import{_ as U}from"./FormInput.48525f12.js";import{m as Se,y as Ve,f as Oe,a as Ce,O as Re,n as Ie,_ as we}from"./Modal.c37b4e9f.js";import{_ as ae}from"./MultiSelect.03a43400.js";import{u as M,c as Pe,l as ee,o as k,K as ke,H,T as de,t as X,N as te,p as Te,a as B}from"./open-closed.5f597199.js";import{b as Ne}from"./use-resolve-button-type.5431d81c.js";import{r as De}from"./ArrowUturnLeftIcon.05ce2cd6.js";import{r as Be}from"./CheckCircleIcon.0d90e487.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.c208199c.js";function Ee(e){throw new Error("Unexpected object: "+e)}var P=(e=>(e[e.First=0]="First",e[e.Previous=1]="Previous",e[e.Next=2]="Next",e[e.Last=3]="Last",e[e.Specific=4]="Specific",e[e.Nothing=5]="Nothing",e))(P||{});function Ae(e,s){let n=s.resolveItems();if(n.length<=0)return null;let o=s.resolveActiveIndex(),t=o!=null?o:-1,l=(()=>{switch(e.focus){case 0:return n.findIndex(r=>!s.resolveDisabled(r));case 1:{let r=n.slice().reverse().findIndex((y,a,u)=>t!==-1&&u.length-a-1>=t?!1:!s.resolveDisabled(y));return r===-1?r:n.length-1-r}case 2:return n.findIndex((r,y)=>y<=t?!1:!s.resolveDisabled(r));case 3:{let r=n.slice().reverse().findIndex(y=>!s.resolveDisabled(y));return r===-1?r:n.length-1-r}case 4:return n.findIndex(r=>s.resolveId(r)===e.id);case 5:return null;default:Ee(e)}})();return l===-1?o:l}function Ue({container:e,accept:s,walk:n,enabled:o}){Z(()=>{let t=e.value;if(!t||o!==void 0&&!o.value)return;let l=Se(e);if(!l)return;let r=Object.assign(a=>s(a),{acceptNode:s}),y=l.createTreeWalker(t,NodeFilter.SHOW_ELEMENT,r,!1);for(;y.nextNode();)n(y.currentNode)})}function ce(e={},s=null,n=[]){for(let[o,t]of Object.entries(e))pe(n,ve(s,o),t);return n}function ve(e,s){return e?e+"["+s+"]":s}function pe(e,s,n){if(Array.isArray(n))for(let[o,t]of n.entries())pe(e,ve(s,o.toString()),t);else n instanceof Date?e.push([s,n.toISOString()]):typeof n=="boolean"?e.push([s,n?"1":"0"]):typeof n=="string"?e.push([s,n]):typeof n=="number"?e.push([s,`${n}`]):n==null?e.push([s,""]):ce(n,s,e)}function Le(e,s,n){let o=w(n==null?void 0:n.value),t=O(()=>e.value!==void 0);return[O(()=>t.value?e.value:o.value),function(l){return t.value||(o.value=l),s==null?void 0:s(l)}]}function se(e){return[e.screenX,e.screenY]}function je(){let e=w([-1,-1]);return{wasMoved(s){let n=se(s);return e.value[0]===n[0]&&e.value[1]===n[1]?!1:(e.value=n,!0)},update(s){e.value=se(s)}}}function Fe(e,s){return e===s}var Me=(e=>(e[e.Open=0]="Open",e[e.Closed=1]="Closed",e))(Me||{}),$e=(e=>(e[e.Single=0]="Single",e[e.Multi=1]="Multi",e))($e||{}),qe=(e=>(e[e.Pointer=0]="Pointer",e[e.Other=1]="Other",e))(qe||{});let me=Symbol("ComboboxContext");function Y(e){let s=xe(me,null);if(s===null){let n=new Error(`<${e} /> is missing a parent <Combobox /> component.`);throw Error.captureStackTrace&&Error.captureStackTrace(n,Y),n}return s}let Ke=G({name:"Combobox",emits:{"update:modelValue":e=>!0},props:{as:{type:[Object,String],default:"template"},disabled:{type:[Boolean],default:!1},by:{type:[String,Function],default:()=>Fe},modelValue:{type:[Object,String,Number,Boolean],default:void 0},defaultValue:{type:[Object,String,Number,Boolean],default:void 0},form:{type:String,optional:!0},name:{type:String,optional:!0},nullable:{type:Boolean,default:!1},multiple:{type:[Boolean],default:!1}},inheritAttrs:!1,setup(e,{slots:s,attrs:n,emit:o}){let t=w(1),l=w(null),r=w(null),y=w(null),a=w(null),u=w({static:!1,hold:!1}),i=w([]),R=w(null),N=w(1),D=w(!1);function $(v=x=>x){let x=R.value!==null?i.value[R.value]:null,g=Re(v(i.value.slice()),C=>k(C.dataRef.domRef)),m=x?g.indexOf(x):null;return m===-1&&(m=null),{options:g,activeOptionIndex:m}}let d=O(()=>e.multiple?1:0),p=O(()=>e.nullable),[b,T]=Le(O(()=>e.modelValue===void 0?M(d.value,{[1]:[],[0]:void 0}):e.modelValue),v=>o("update:modelValue",v),O(()=>e.defaultValue)),f={comboboxState:t,value:b,mode:d,compare(v,x){if(typeof e.by=="string"){let g=e.by;return(v==null?void 0:v[g])===(x==null?void 0:x[g])}return e.by(v,x)},defaultValue:O(()=>e.defaultValue),nullable:p,inputRef:r,labelRef:l,buttonRef:y,optionsRef:a,disabled:O(()=>e.disabled),options:i,change(v){T(v)},activeOptionIndex:O(()=>{if(D.value&&R.value===null&&i.value.length>0){let v=i.value.findIndex(x=>!x.dataRef.disabled);if(v!==-1)return v}return R.value}),activationTrigger:N,optionsPropsRef:u,closeCombobox(){D.value=!1,!e.disabled&&t.value!==1&&(t.value=1,R.value=null)},openCombobox(){if(D.value=!0,e.disabled||t.value===0)return;let v=i.value.findIndex(x=>{let g=S(x.dataRef.value);return M(d.value,{[0]:()=>f.compare(S(f.value.value),S(g)),[1]:()=>S(f.value.value).some(m=>f.compare(S(m),S(g)))})});v!==-1&&(R.value=v),t.value=0},goToOption(v,x,g){if(D.value=!1,e.disabled||a.value&&!u.value.static&&t.value===1)return;let m=$();if(m.activeOptionIndex===null){let I=m.options.findIndex(W=>!W.dataRef.disabled);I!==-1&&(m.activeOptionIndex=I)}let C=Ae(v===P.Specific?{focus:P.Specific,id:x}:{focus:v},{resolveItems:()=>m.options,resolveActiveIndex:()=>m.activeOptionIndex,resolveId:I=>I.id,resolveDisabled:I=>I.dataRef.disabled});R.value=C,N.value=g!=null?g:1,i.value=m.options},selectOption(v){let x=i.value.find(m=>m.id===v);if(!x)return;let{dataRef:g}=x;T(M(d.value,{[0]:()=>g.value,[1]:()=>{let m=S(f.value.value).slice(),C=S(g.value),I=m.findIndex(W=>f.compare(C,S(W)));return I===-1?m.push(C):m.splice(I,1),m}}))},selectActiveOption(){if(f.activeOptionIndex.value===null)return;let{dataRef:v,id:x}=i.value[f.activeOptionIndex.value];T(M(d.value,{[0]:()=>v.value,[1]:()=>{let g=S(f.value.value).slice(),m=S(v.value),C=g.findIndex(I=>f.compare(m,S(I)));return C===-1?g.push(m):g.splice(C,1),g}})),f.goToOption(P.Specific,x)},registerOption(v,x){let g={id:v,dataRef:x},m=$(C=>[...C,g]);if(R.value===null){let C=x.value.value;M(d.value,{[0]:()=>f.compare(S(f.value.value),S(C)),[1]:()=>S(f.value.value).some(I=>f.compare(S(I),S(C)))})&&(m.activeOptionIndex=m.options.indexOf(g))}i.value=m.options,R.value=m.activeOptionIndex,N.value=1},unregisterOption(v){var x;f.activeOptionIndex.value!==null&&((x=f.options.value[f.activeOptionIndex.value])==null?void 0:x.id)===v&&(D.value=!0);let g=$(m=>{let C=m.findIndex(I=>I.id===v);return C!==-1&&m.splice(C,1),m});i.value=g.options,R.value=g.activeOptionIndex,N.value=1}};Ve([r,y,a],()=>f.closeCombobox(),O(()=>t.value===0)),fe(me,f),Pe(O(()=>M(t.value,{[0]:ee.Open,[1]:ee.Closed})));let q=O(()=>f.activeOptionIndex.value===null?null:i.value[f.activeOptionIndex.value].dataRef.value),L=O(()=>{var v;return(v=k(r))==null?void 0:v.closest("form")});return oe(()=>{ne([L],()=>{if(!L.value||e.defaultValue===void 0)return;function v(){f.change(e.defaultValue)}return L.value.addEventListener("reset",v),()=>{var x;(x=L.value)==null||x.removeEventListener("reset",v)}},{immediate:!0})}),()=>{let{name:v,disabled:x,form:g,...m}=e,C={open:t.value===0,disabled:x,activeIndex:f.activeOptionIndex.value,activeOption:q.value,value:b.value};return ue(ie,[...v!=null&&b.value!=null?ce({[v]:b.value}).map(([I,W])=>ue(Oe,ke({features:Ce.Hidden,key:I,as:"input",type:"hidden",hidden:!0,readOnly:!0,form:g,name:I,value:W}))):[],H({theirProps:{...n,...de(m,["modelValue","defaultValue","nullable","multiple","onUpdate:modelValue","by"])},ourProps:{},slot:C,slots:s,attrs:n,name:"Combobox"})])}}}),ze=G({name:"ComboboxLabel",props:{as:{type:[Object,String],default:"label"},id:{type:String,default:()=>`headlessui-combobox-label-${X()}`}},setup(e,{attrs:s,slots:n}){let o=Y("ComboboxLabel");function t(){var l;(l=k(o.inputRef))==null||l.focus({preventScroll:!0})}return()=>{let l={open:o.comboboxState.value===0,disabled:o.disabled.value},{id:r,...y}=e,a={id:r,ref:o.labelRef,onClick:t};return H({ourProps:a,theirProps:y,slot:l,attrs:s,slots:n,name:"ComboboxLabel"})}}}),Ge=G({name:"ComboboxButton",props:{as:{type:[Object,String],default:"button"},id:{type:String,default:()=>`headlessui-combobox-button-${X()}`}},setup(e,{attrs:s,slots:n,expose:o}){let t=Y("ComboboxButton");o({el:t.buttonRef,$el:t.buttonRef});function l(a){t.disabled.value||(t.comboboxState.value===0?t.closeCombobox():(a.preventDefault(),t.openCombobox()),K(()=>{var u;return(u=k(t.inputRef))==null?void 0:u.focus({preventScroll:!0})}))}function r(a){switch(a.key){case B.ArrowDown:a.preventDefault(),a.stopPropagation(),t.comboboxState.value===1&&t.openCombobox(),K(()=>{var u;return(u=t.inputRef.value)==null?void 0:u.focus({preventScroll:!0})});return;case B.ArrowUp:a.preventDefault(),a.stopPropagation(),t.comboboxState.value===1&&(t.openCombobox(),K(()=>{t.value.value||t.goToOption(P.Last)})),K(()=>{var u;return(u=t.inputRef.value)==null?void 0:u.focus({preventScroll:!0})});return;case B.Escape:if(t.comboboxState.value!==0)return;a.preventDefault(),t.optionsRef.value&&!t.optionsPropsRef.value.static&&a.stopPropagation(),t.closeCombobox(),K(()=>{var u;return(u=t.inputRef.value)==null?void 0:u.focus({preventScroll:!0})});return}}let y=Ne(O(()=>({as:e.as,type:s.type})),t.buttonRef);return()=>{var a,u;let i={open:t.comboboxState.value===0,disabled:t.disabled.value,value:t.value.value},{id:R,...N}=e,D={ref:t.buttonRef,id:R,type:y.value,tabindex:"-1","aria-haspopup":"listbox","aria-controls":(a=k(t.optionsRef))==null?void 0:a.id,"aria-expanded":t.disabled.value?void 0:t.comboboxState.value===0,"aria-labelledby":t.labelRef.value?[(u=k(t.labelRef))==null?void 0:u.id,R].join(" "):void 0,disabled:t.disabled.value===!0?!0:void 0,onKeydown:r,onClick:l};return H({ourProps:D,theirProps:N,slot:i,attrs:s,slots:n,name:"ComboboxButton"})}}}),He=G({name:"ComboboxInput",props:{as:{type:[Object,String],default:"input"},static:{type:Boolean,default:!1},unmount:{type:Boolean,default:!0},displayValue:{type:Function},defaultValue:{type:String,default:void 0},id:{type:String,default:()=>`headlessui-combobox-input-${X()}`}},emits:{change:e=>!0},setup(e,{emit:s,attrs:n,slots:o,expose:t}){let l=Y("ComboboxInput"),r={value:!1};t({el:l.inputRef,$el:l.inputRef});let y=O(()=>{var d;let p=l.value.value;return k(l.inputRef)?typeof e.displayValue<"u"&&p!==void 0?(d=e.displayValue(p))!=null?d:"":typeof p=="string"?p:"":""});oe(()=>{ne([y,l.comboboxState],([d,p],[b,T])=>{if(r.value)return;let f=k(l.inputRef);f&&(T===0&&p===1||d!==b)&&(f.value=d)},{immediate:!0}),ne([l.comboboxState],([d],[p])=>{if(d===0&&p===1){let b=k(l.inputRef);if(!b)return;let T=b.value,{selectionStart:f,selectionEnd:q,selectionDirection:L}=b;b.value="",b.value=T,L!==null?b.setSelectionRange(f,q,L):b.setSelectionRange(f,q)}})});let a=w(!1);function u(){a.value=!0}function i(){setTimeout(()=>{a.value=!1})}function R(d){switch(r.value=!0,d.key){case B.Backspace:case B.Delete:if(l.mode.value!==0||!l.nullable.value)return;let p=d.currentTarget;requestAnimationFrame(()=>{if(p.value===""){l.change(null);let b=k(l.optionsRef);b&&(b.scrollTop=0),l.goToOption(P.Nothing)}});break;case B.Enter:if(r.value=!1,l.comboboxState.value!==0||a.value)return;if(d.preventDefault(),d.stopPropagation(),l.activeOptionIndex.value===null){l.closeCombobox();return}l.selectActiveOption(),l.mode.value===0&&l.closeCombobox();break;case B.ArrowDown:return r.value=!1,d.preventDefault(),d.stopPropagation(),M(l.comboboxState.value,{[0]:()=>l.goToOption(P.Next),[1]:()=>l.openCombobox()});case B.ArrowUp:return r.value=!1,d.preventDefault(),d.stopPropagation(),M(l.comboboxState.value,{[0]:()=>l.goToOption(P.Previous),[1]:()=>{l.openCombobox(),K(()=>{l.value.value||l.goToOption(P.Last)})}});case B.Home:if(d.shiftKey)break;return r.value=!1,d.preventDefault(),d.stopPropagation(),l.goToOption(P.First);case B.PageUp:return r.value=!1,d.preventDefault(),d.stopPropagation(),l.goToOption(P.First);case B.End:if(d.shiftKey)break;return r.value=!1,d.preventDefault(),d.stopPropagation(),l.goToOption(P.Last);case B.PageDown:return r.value=!1,d.preventDefault(),d.stopPropagation(),l.goToOption(P.Last);case B.Escape:if(r.value=!1,l.comboboxState.value!==0)return;d.preventDefault(),l.optionsRef.value&&!l.optionsPropsRef.value.static&&d.stopPropagation(),l.closeCombobox();break;case B.Tab:if(r.value=!1,l.comboboxState.value!==0)return;l.mode.value===0&&l.selectActiveOption(),l.closeCombobox();break}}function N(d){l.openCombobox(),s("change",d)}function D(){r.value=!1}let $=O(()=>{var d,p,b,T;return(T=(b=(p=e.defaultValue)!=null?p:l.defaultValue.value!==void 0?(d=e.displayValue)==null?void 0:d.call(e,l.defaultValue.value):null)!=null?b:l.defaultValue.value)!=null?T:""});return()=>{var d,p,b,T,f,q;let L={open:l.comboboxState.value===0},{id:v,displayValue:x,onChange:g,...m}=e,C={"aria-controls":(d=l.optionsRef.value)==null?void 0:d.id,"aria-expanded":l.disabled.value?void 0:l.comboboxState.value===0,"aria-activedescendant":l.activeOptionIndex.value===null||(p=l.options.value[l.activeOptionIndex.value])==null?void 0:p.id,"aria-labelledby":(f=(b=k(l.labelRef))==null?void 0:b.id)!=null?f:(T=k(l.buttonRef))==null?void 0:T.id,"aria-autocomplete":"list",id:v,onCompositionstart:u,onCompositionend:i,onKeydown:R,onInput:N,onBlur:D,role:"combobox",type:(q=n.type)!=null?q:"text",tabIndex:0,ref:l.inputRef,defaultValue:$.value,disabled:l.disabled.value===!0?!0:void 0};return H({ourProps:C,theirProps:m,slot:L,attrs:n,slots:o,features:te.RenderStrategy|te.Static,name:"ComboboxInput"})}}}),Ye=G({name:"ComboboxOptions",props:{as:{type:[Object,String],default:"ul"},static:{type:Boolean,default:!1},unmount:{type:Boolean,default:!0},hold:{type:[Boolean],default:!1}},setup(e,{attrs:s,slots:n,expose:o}){let t=Y("ComboboxOptions"),l=`headlessui-combobox-options-${X()}`;o({el:t.optionsRef,$el:t.optionsRef}),Z(()=>{t.optionsPropsRef.value.static=e.static}),Z(()=>{t.optionsPropsRef.value.hold=e.hold});let r=Te(),y=O(()=>r!==null?(r.value&ee.Open)===ee.Open:t.comboboxState.value===0);return Ue({container:O(()=>k(t.optionsRef)),enabled:O(()=>t.comboboxState.value===0),accept(a){return a.getAttribute("role")==="option"?NodeFilter.FILTER_REJECT:a.hasAttribute("role")?NodeFilter.FILTER_SKIP:NodeFilter.FILTER_ACCEPT},walk(a){a.setAttribute("role","none")}}),()=>{var a,u,i;let R={open:t.comboboxState.value===0},N={"aria-labelledby":(i=(a=k(t.labelRef))==null?void 0:a.id)!=null?i:(u=k(t.buttonRef))==null?void 0:u.id,id:l,ref:t.optionsRef,role:"listbox","aria-multiselectable":t.mode.value===1?!0:void 0},D=de(e,["hold"]);return H({ourProps:N,theirProps:D,slot:R,attrs:s,slots:n,features:te.RenderStrategy|te.Static,visible:y.value,name:"ComboboxOptions"})}}}),We=G({name:"ComboboxOption",props:{as:{type:[Object,String],default:"li"},value:{type:[Object,String,Number,Boolean]},disabled:{type:Boolean,default:!1}},setup(e,{slots:s,attrs:n,expose:o}){let t=Y("ComboboxOption"),l=`headlessui-combobox-option-${X()}`,r=w(null);o({el:r,$el:r});let y=O(()=>t.activeOptionIndex.value!==null?t.options.value[t.activeOptionIndex.value].id===l:!1),a=O(()=>M(t.mode.value,{[0]:()=>t.compare(S(t.value.value),S(e.value)),[1]:()=>S(t.value.value).some(p=>t.compare(S(p),S(e.value)))})),u=O(()=>({disabled:e.disabled,value:e.value,domRef:r}));oe(()=>t.registerOption(l,u)),be(()=>t.unregisterOption(l)),Z(()=>{t.comboboxState.value===0&&y.value&&t.activationTrigger.value!==0&&K(()=>{var p,b;return(b=(p=k(r))==null?void 0:p.scrollIntoView)==null?void 0:b.call(p,{block:"nearest"})})});function i(p){if(e.disabled)return p.preventDefault();t.selectOption(l),t.mode.value===0&&t.closeCombobox(),Ie()||requestAnimationFrame(()=>{var b;return(b=k(t.inputRef))==null?void 0:b.focus()})}function R(){if(e.disabled)return t.goToOption(P.Nothing);t.goToOption(P.Specific,l)}let N=je();function D(p){N.update(p)}function $(p){N.wasMoved(p)&&(e.disabled||y.value||t.goToOption(P.Specific,l,0))}function d(p){N.wasMoved(p)&&(e.disabled||y.value&&(t.optionsPropsRef.value.hold||t.goToOption(P.Nothing)))}return()=>{let{disabled:p}=e,b={active:y.value,selected:a.value,disabled:p},T={id:l,ref:r,role:"option",tabIndex:p===!0?void 0:-1,"aria-disabled":p===!0?!0:void 0,"aria-selected":a.value,disabled:void 0,onClick:i,onFocus:R,onPointerenter:D,onMouseenter:D,onPointermove:$,onMousemove:$,onPointerleave:d,onMouseleave:d};return H({ourProps:T,theirProps:e,slot:b,attrs:n,slots:s,name:"ComboboxOption"})}}});function Je(e,s){return A(),F("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[c("path",{d:"M6.5 9a2.5 2.5 0 115 0 2.5 2.5 0 01-5 0z"}),c("path",{"fill-rule":"evenodd",d:"M10 18a8 8 0 100-16 8 8 0 000 16zM9 5a4 4 0 102.248 7.309l1.472 1.471a.75.75 0 101.06-1.06l-1.471-1.472A4 4 0 009 5z","clip-rule":"evenodd"})])}const Xe={class:"flex space-x-1"},Qe={key:0,class:"text-red-500"},Ze={class:"relative mt-1"},et=["onClick"],tt={class:"block truncate"},ot={__name:"SearchAddressInput",props:{modelValue:String,required:[Boolean,String]},emits:["update:modelValue","selected"],setup(e,{emit:s}){const n=w([]),o=_.debounce(async r=>{const y="https://developers.onemap.sg/commonapi/search?searchVal="+r.target.value+"&returnGeom=Y&getAddrDetails=Y";let a=await(await fetch(y)).json();a&&(n.value=await a.results)},300);function t(r){s("update:modelValue",r.target.value),o(r)}function l(r){s("selected",r)}return(r,y)=>(A(),Q(j(Ke),{as:"div"},{default:V(()=>[c("div",Xe,[h(j(ze),{class:"block text-sm font-medium text-gray-700"},{default:V(()=>[_e(r.$slots,"default")]),_:3}),e.required?(A(),F("span",Qe," * ")):z("",!0)]),c("div",Ze,[h(j(He),{class:"w-full rounded-md border border-gray-300 bg-white py-2 pl-3 pr-10 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm",onInput:t,value:e.modelValue},null,8,["value"]),h(j(Ge),{class:"absolute inset-y-0 right-0 flex items-center rounded-r-md px-2 focus:outline-none"},{default:V(()=>[h(j(Je),{class:"h-5 w-5 text-gray-400","aria-hidden":"true"})]),_:1}),n.value.length>0?(A(),Q(j(Ye),{key:0,class:"absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm"},{default:V(()=>[(A(!0),F(ie,null,ye(n.value,a=>(A(),Q(j(We),{as:"template"},{default:V(()=>[c("li",{class:"relative cursor-default select-none py-2 pl-3 pr-9 hover:bg-gray-100",onClick:u=>l(a)},[c("span",tt,J(a.ADDRESS),1)],8,et)]),_:2},1024))),256))]),_:1})):z("",!0)])]),_:3}))}},lt={class:"flex flex-col md:flex-row space-x-2"},at={key:0,class:"text-gray-600"},nt={key:1},ut={key:2,class:"text-gray-600"},rt=["onSubmit"],st={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},it={class:"sm:col-span-4"},dt={class:"sm:col-span-2"},ct={class:"sm:col-span-3"},vt={class:"sm:col-span-3"},pt=c("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Base Currency ",-1),mt={key:0,class:"text-sm text-red-600"},ft=c("div",{class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},[c("div",{class:"relative"},[c("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[c("div",{class:"w-full border-t border-gray-300"})]),c("div",{class:"relative flex justify-center"},[c("span",{class:"px-4 bg-white text-lg font-medium text-gray-900"}," Contact ")])])],-1),bt={class:"sm:col-span-3"},xt={class:"sm:col-span-3"},_t={class:"sm:col-span-2"},yt=c("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Phone Code ",-1),gt={key:0,class:"text-sm text-red-600"},ht={class:"sm:col-span-4"},St=c("div",{class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},[c("div",{class:"relative"},[c("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[c("div",{class:"w-full border-t border-gray-300"})]),c("div",{class:"relative flex justify-center"},[c("span",{class:"px-3 bg-white text-lg font-medium text-gray-900"}," Address ")])])],-1),Vt={class:"sm:col-span-6"},Ot={class:"sm:col-span-3"},Ct={class:"sm:col-span-3"},Rt={class:"sm:col-span-3"},It={class:"sm:col-span-3"},wt={class:"sm:col-span-3"},Pt=c("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Country ",-1),kt={key:0,class:"text-sm text-red-600"},Tt={class:"sm:col-span-3 hidden"},Nt={class:"sm:col-span-3 hidden"},Dt={class:"sm:col-span-6"},Bt={class:"flex space-x-1 mt-5 justify-end"},Et=c("span",null," Back ",-1),At=c("span",null," Save ",-1),Ht={__name:"Form",props:{profile:Object,countries:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(e,{emit:s}){const n=e,o=w(le(l())),t=w([]);oe(()=>{t.value=n.countries.data,o.value=n.profile?le(n.profile):le(l()),n.type==="create"&&(o.value.base_currency_id=o.value.base_currency_id?o.value.base_currency_id:t.value[0],o.value.address.country_id=o.value.address.country_id?o.value.address.country_id:t.value[0],o.value.contact.phone_country_id=o.value.contact.phone_country_id?o.value.contact.phone_country_id:t.value[0])});function l(){return{alias:"",name:"",uen:"",base_currency_id:"",address:{block_num:"",building:"",country_id:"",latitude:"",longitude:"",postcode:"",street_name:"",unit_num:""},contact:{name:"",email:"",phone_country_id:"",phone_num:""}}}function r(a){o.value.address={block_num:a.BLK_NO,building:a.BUILDING,country_id:t.value[0],latitude:a.LATITUDE,longitude:a.LONGTITUDE,postcode:a.POSTAL,street_name:a.ROAD_NAME,unit_num:""}}function y(){o.value.clearErrors(),n.type==="create"&&o.value.transform(a=>({...a,base_currency_id:a.base_currency_id.id,address:{...a.address,country_id:a.address.country_id.id},contact:{...a.contact,phone_country_id:a.contact.phone_country_id.id}})).post("/profiles/create",{onSuccess:()=>{s("modalClose")},preserveState:!0,replace:!0}),n.type==="update"&&o.value.transform(a=>({...a,base_currency_id:a.base_currency_id.id,address:{...a.address,country_id:a.address.country_id.id},contact:{...a.contact,phone_country_id:a.contact.phone_country_id.id}})).post("/profiles/"+o.value.id+"/update",{onSuccess:()=>{s("modalClose")},preserveState:!0,replace:!0})}return(a,u)=>(A(),Q(ge,{to:"body"},[h(we,{open:e.showModal,onModalClose:u[17]||(u[17]=i=>a.$emit("modalClose"))},{header:V(()=>[c("div",lt,[n.profile?(A(),F("span",at," Editing ")):z("",!0),n.profile?(A(),F("span",nt,J(n.profile.name),1)):(A(),F("span",ut," Create New Profile "))])]),default:V(()=>[c("form",{onSubmit:he(y,["prevent"]),id:"submit"},[c("div",st,[c("div",it,[h(U,{modelValue:o.value.name,"onUpdate:modelValue":u[0]||(u[0]=i=>o.value.name=i),error:o.value.errors.name,required:"true"},{default:V(()=>[E(" Name ")]),_:1},8,["modelValue","error"])]),c("div",dt,[h(U,{modelValue:o.value.alias,"onUpdate:modelValue":u[1]||(u[1]=i=>o.value.alias=i),error:o.value.errors.alias},{default:V(()=>[E(" Alias ")]),_:1},8,["modelValue","error"])]),c("div",ct,[h(U,{modelValue:o.value.uen,"onUpdate:modelValue":u[2]||(u[2]=i=>o.value.uen=i),error:o.value.errors.uen},{default:V(()=>[E(" UEN ")]),_:1},8,["modelValue","error"])]),c("div",vt,[pt,h(ae,{modelValue:o.value.base_currency_id,"onUpdate:modelValue":u[3]||(u[3]=i=>o.value.base_currency_id=i),options:t.value,trackBy:"id",valueProp:"id",label:"currency_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),o.value.errors.base_currency_id?(A(),F("div",mt,J(o.value.errors.base_currency_id),1)):z("",!0)]),ft,c("div",bt,[h(U,{modelValue:o.value.contact.name,"onUpdate:modelValue":u[4]||(u[4]=i=>o.value.contact.name=i),error:o.value.errors["contact.name"]},{default:V(()=>[E(" Name ")]),_:1},8,["modelValue","error"])]),c("div",xt,[h(U,{modelValue:o.value.contact.email,"onUpdate:modelValue":u[5]||(u[5]=i=>o.value.contact.email=i),error:o.value.errors["contact.email"]},{default:V(()=>[E(" Email ")]),_:1},8,["modelValue","error"])]),c("div",_t,[yt,h(ae,{modelValue:o.value.contact.phone_country_id,"onUpdate:modelValue":u[6]||(u[6]=i=>o.value.contact.phone_country_id=i),options:t.value,trackBy:"id",valueProp:"id",label:"phone_code",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),o.value.errors["contact.phone_country_id"]?(A(),F("div",gt,J(o.value.errors["contact.phone_country_id"]),1)):z("",!0)]),c("div",ht,[h(U,{modelValue:o.value.contact.phone_num,"onUpdate:modelValue":u[7]||(u[7]=i=>o.value.contact.phone_num=i),required:"true",error:o.value.errors["contact.phone_num"]},{default:V(()=>[E(" Phone Number ")]),_:1},8,["modelValue","error"])]),St,c("div",Vt,[h(ot,{modelValue:o.value.address.postcode,"onUpdate:modelValue":u[8]||(u[8]=i=>o.value.address.postcode=i),onSelected:r,required:"true",error:o.value.errors["address.postcode"]},{default:V(()=>[E(" Postcode ")]),_:1},8,["modelValue","error"])]),c("div",Ot,[h(U,{modelValue:o.value.address.unit_num,"onUpdate:modelValue":u[9]||(u[9]=i=>o.value.address.unit_num=i),required:"true",error:o.value.errors["address.unit_num"]},{default:V(()=>[E(" Unit Num ")]),_:1},8,["modelValue","error"])]),c("div",Ct,[h(U,{modelValue:o.value.address.block_num,"onUpdate:modelValue":u[10]||(u[10]=i=>o.value.address.block_num=i),error:o.value.errors["address.block_num"]},{default:V(()=>[E(" Block Num ")]),_:1},8,["modelValue","error"])]),c("div",Rt,[h(U,{modelValue:o.value.address.building,"onUpdate:modelValue":u[11]||(u[11]=i=>o.value.address.building=i),error:o.value.errors["address.building"]},{default:V(()=>[E(" Building Name ")]),_:1},8,["modelValue","error"])]),c("div",It,[h(U,{modelValue:o.value.address.street_name,"onUpdate:modelValue":u[12]||(u[12]=i=>o.value.address.street_name=i),required:"true",error:o.value.errors["address.street_name"]},{default:V(()=>[E(" Street Name ")]),_:1},8,["modelValue","error"])]),c("div",wt,[Pt,h(ae,{modelValue:o.value.address.country_id,"onUpdate:modelValue":u[13]||(u[13]=i=>o.value.address.country_id=i),options:t.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),o.value.errors["address.country_id"]?(A(),F("div",kt,J(o.value.errors["address.country_id"]),1)):z("",!0)]),c("div",Tt,[h(U,{modelValue:o.value.address.latitude,"onUpdate:modelValue":u[14]||(u[14]=i=>o.value.address.latitude=i)},{default:V(()=>[E(" Latitude ")]),_:1},8,["modelValue"])]),c("div",Nt,[h(U,{modelValue:o.value.address.longitude,"onUpdate:modelValue":u[15]||(u[15]=i=>o.value.address.longitude=i)},{default:V(()=>[E(" Longitude ")]),_:1},8,["modelValue"])])]),c("div",Dt,[c("div",Bt,[h(re,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:u[16]||(u[16]=i=>a.$emit("modalClose")),form:"submit"},{default:V(()=>[h(j(De),{class:"w-4 h-4"}),Et]),_:1}),h(re,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:V(()=>[h(j(Be),{class:"w-4 h-4"}),At]),_:1})])])],40,rt)]),_:1},8,["open"])]))}};export{Ht as default};

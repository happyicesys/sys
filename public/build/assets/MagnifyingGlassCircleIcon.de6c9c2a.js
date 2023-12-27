import{u as j,o as I,c as ae,l as q,K as ne,H as M,T as Q,t as _,N as z,p as ue,a as V}from"./open-closed.b24bbe75.js";import{b as ie}from"./use-resolve-button-type.eab69395.js";import{m as Z,y as re,f as se,a as ve,O as de,o as pe,n as ce}from"./disposables.a27cf54e.js";import{q as H,g as A,j as g,s as $,aa as O,A as be,h as Y,z as W,y as J,F as fe,x as me,E as L,B as xe,o as ge,f as Oe,b as X}from"./app.d127c760.js";function Se(e){throw new Error("Unexpected object: "+e)}var w=(e=>(e[e.First=0]="First",e[e.Previous=1]="Previous",e[e.Next=2]="Next",e[e.Last=3]="Last",e[e.Specific=4]="Specific",e[e.Nothing=5]="Nothing",e))(w||{});function he(e,n){let l=n.resolveItems();if(l.length<=0)return null;let b=n.resolveActiveIndex(),t=b!=null?b:-1,o=(()=>{switch(e.focus){case 0:return l.findIndex(v=>!n.resolveDisabled(v));case 1:{let v=l.slice().reverse().findIndex((i,r,f)=>t!==-1&&f.length-r-1>=t?!1:!n.resolveDisabled(i));return v===-1?v:l.length-1-v}case 2:return l.findIndex((v,i)=>i<=t?!1:!n.resolveDisabled(v));case 3:{let v=l.slice().reverse().findIndex(i=>!n.resolveDisabled(i));return v===-1?v:l.length-1-v}case 4:return l.findIndex(v=>n.resolveId(v)===e.id);case 5:return null;default:Se(e)}})();return o===-1?b:o}function Re({container:e,accept:n,walk:l,enabled:b}){H(()=>{let t=e.value;if(!t||b!==void 0&&!b.value)return;let o=Z(e);if(!o)return;let v=Object.assign(r=>n(r),{acceptNode:n}),i=o.createTreeWalker(t,NodeFilter.SHOW_ELEMENT,v,!1);for(;i.nextNode();)l(i.currentNode)})}function ee(e={},n=null,l=[]){for(let[b,t]of Object.entries(e))oe(l,te(n,b),t);return l}function te(e,n){return e?e+"["+n+"]":n}function oe(e,n,l){if(Array.isArray(l))for(let[b,t]of l.entries())oe(e,te(n,b.toString()),t);else l instanceof Date?e.push([n,l.toISOString()]):typeof l=="boolean"?e.push([n,l?"1":"0"]):typeof l=="string"?e.push([n,l]):typeof l=="number"?e.push([n,`${l}`]):l==null?e.push([n,""]):ee(l,n,e)}function ye(e,n,l){let b=A(l==null?void 0:l.value),t=g(()=>e.value!==void 0);return[g(()=>t.value?e.value:b.value),function(o){return t.value||(b.value=o),n==null?void 0:n(o)}]}function G(e){return[e.screenX,e.screenY]}function Ie(){let e=A([-1,-1]);return{wasMoved(n){let l=G(n);return e.value[0]===l[0]&&e.value[1]===l[1]?!1:(e.value=l,!0)},update(n){e.value=G(n)}}}function Ce(e,n){return e===n}var Pe=(e=>(e[e.Open=0]="Open",e[e.Closed=1]="Closed",e))(Pe||{}),we=(e=>(e[e.Single=0]="Single",e[e.Multi=1]="Multi",e))(we||{}),Te=(e=>(e[e.Pointer=0]="Pointer",e[e.Other=1]="Other",e))(Te||{});let le=Symbol("ComboboxContext");function K(e){let n=xe(le,null);if(n===null){let l=new Error(`<${e} /> is missing a parent <Combobox /> component.`);throw Error.captureStackTrace&&Error.captureStackTrace(l,K),l}return n}let ke=$({name:"Combobox",emits:{"update:modelValue":e=>!0},props:{as:{type:[Object,String],default:"template"},disabled:{type:[Boolean],default:!1},by:{type:[String,Function],default:()=>Ce},modelValue:{type:[Object,String,Number,Boolean],default:void 0},defaultValue:{type:[Object,String,Number,Boolean],default:void 0},form:{type:String,optional:!0},name:{type:String,optional:!0},nullable:{type:Boolean,default:!1},multiple:{type:[Boolean],default:!1}},inheritAttrs:!1,setup(e,{slots:n,attrs:l,emit:b}){let t=A(1),o=A(null),v=A(null),i=A(null),r=A(null),f=A({static:!1,hold:!1}),x=A([]),S=A(null),T=A(1),E=A(!1);function B(u=p=>p){let p=S.value!==null?x.value[S.value]:null,c=de(u(x.value.slice()),m=>I(m.dataRef.domRef)),s=p?c.indexOf(p):null;return s===-1&&(s=null),{options:c,activeOptionIndex:s}}let k=g(()=>e.multiple?1:0),h=g(()=>e.nullable),[a,R]=ye(g(()=>e.modelValue),u=>b("update:modelValue",u),g(()=>e.defaultValue)),y=g(()=>a.value===void 0?j(k.value,{[1]:[],[0]:void 0}):a.value),F=null,C=null,d={comboboxState:t,value:y,mode:k,compare(u,p){if(typeof e.by=="string"){let c=e.by;return(u==null?void 0:u[c])===(p==null?void 0:p[c])}return e.by(u,p)},defaultValue:g(()=>e.defaultValue),nullable:h,inputRef:v,labelRef:o,buttonRef:i,optionsRef:r,disabled:g(()=>e.disabled),options:x,change(u){R(u)},activeOptionIndex:g(()=>{if(E.value&&S.value===null&&x.value.length>0){let u=x.value.findIndex(p=>!p.dataRef.disabled);u!==-1&&(S.value=u)}return S.value}),activationTrigger:T,optionsPropsRef:f,closeCombobox(){E.value=!1,!e.disabled&&t.value!==1&&(t.value=1,S.value=null)},openCombobox(){if(E.value=!0,e.disabled||t.value===0)return;let u=x.value.findIndex(p=>{let c=O(p.dataRef.value);return j(k.value,{[0]:()=>d.compare(O(d.value.value),O(c)),[1]:()=>O(d.value.value).some(s=>d.compare(O(s),O(c)))})});u!==-1&&(S.value=u),t.value=0},goToOption(u,p,c){E.value=!1,F!==null&&cancelAnimationFrame(F),F=requestAnimationFrame(()=>{if(e.disabled||r.value&&!f.value.static&&t.value===1)return;let s=B();if(s.activeOptionIndex===null){let P=s.options.findIndex(U=>!U.dataRef.disabled);P!==-1&&(s.activeOptionIndex=P)}let m=he(u===w.Specific?{focus:w.Specific,id:p}:{focus:u},{resolveItems:()=>s.options,resolveActiveIndex:()=>s.activeOptionIndex,resolveId:P=>P.id,resolveDisabled:P=>P.dataRef.disabled});S.value=m,T.value=c!=null?c:1,x.value=s.options})},selectOption(u){let p=x.value.find(s=>s.id===u);if(!p)return;let{dataRef:c}=p;R(j(k.value,{[0]:()=>c.value,[1]:()=>{let s=O(d.value.value).slice(),m=O(c.value),P=s.findIndex(U=>d.compare(m,O(U)));return P===-1?s.push(m):s.splice(P,1),s}}))},selectActiveOption(){if(d.activeOptionIndex.value===null)return;let{dataRef:u,id:p}=x.value[d.activeOptionIndex.value];R(j(k.value,{[0]:()=>u.value,[1]:()=>{let c=O(d.value.value).slice(),s=O(u.value),m=c.findIndex(P=>d.compare(s,O(P)));return m===-1?c.push(s):c.splice(m,1),c}})),d.goToOption(w.Specific,p)},registerOption(u,p){C&&cancelAnimationFrame(C);let c={id:u,dataRef:p},s=B(m=>(m.push(c),m));if(S.value===null){let m=p.value.value;j(k.value,{[0]:()=>d.compare(O(d.value.value),O(m)),[1]:()=>O(d.value.value).some(P=>d.compare(O(P),O(m)))})&&(s.activeOptionIndex=s.options.indexOf(c))}x.value=s.options,S.value=s.activeOptionIndex,T.value=1,s.options.some(m=>!I(m.dataRef.domRef))&&(C=requestAnimationFrame(()=>{let m=B();x.value=m.options,S.value=m.activeOptionIndex}))},unregisterOption(u){var p;d.activeOptionIndex.value!==null&&((p=d.options.value[d.activeOptionIndex.value])==null?void 0:p.id)===u&&(E.value=!0);let c=B(s=>{let m=s.findIndex(P=>P.id===u);return m!==-1&&s.splice(m,1),s});x.value=c.options,S.value=c.activeOptionIndex,T.value=1}};re([v,i,r],()=>d.closeCombobox(),g(()=>t.value===0)),be(le,d),ae(g(()=>j(t.value,{[0]:q.Open,[1]:q.Closed})));let D=g(()=>d.activeOptionIndex.value===null?null:x.value[d.activeOptionIndex.value].dataRef.value),N=g(()=>{var u;return(u=I(v))==null?void 0:u.closest("form")});return Y(()=>{W([N],()=>{if(!N.value||e.defaultValue===void 0)return;function u(){d.change(e.defaultValue)}return N.value.addEventListener("reset",u),()=>{var p;(p=N.value)==null||p.removeEventListener("reset",u)}},{immediate:!0})}),()=>{let{name:u,disabled:p,form:c,...s}=e,m={open:t.value===0,disabled:p,activeIndex:d.activeOptionIndex.value,activeOption:D.value,value:y.value};return J(fe,[...u!=null&&y.value!=null?ee({[u]:y.value}).map(([P,U])=>J(se,ne({features:ve.Hidden,key:P,as:"input",type:"hidden",hidden:!0,readOnly:!0,form:c,name:P,value:U}))):[],M({theirProps:{...l,...Q(s,["modelValue","defaultValue","nullable","multiple","onUpdate:modelValue","by"])},ourProps:{},slot:m,slots:n,attrs:l,name:"Combobox"})])}}}),De=$({name:"ComboboxLabel",props:{as:{type:[Object,String],default:"label"},id:{type:String,default:()=>`headlessui-combobox-label-${_()}`}},setup(e,{attrs:n,slots:l}){let b=K("ComboboxLabel");function t(){var o;(o=I(b.inputRef))==null||o.focus({preventScroll:!0})}return()=>{let o={open:b.comboboxState.value===0,disabled:b.disabled.value},{id:v,...i}=e,r={id:v,ref:b.labelRef,onClick:t};return M({ourProps:r,theirProps:i,slot:o,attrs:n,slots:l,name:"ComboboxLabel"})}}}),Be=$({name:"ComboboxButton",props:{as:{type:[Object,String],default:"button"},id:{type:String,default:()=>`headlessui-combobox-button-${_()}`}},setup(e,{attrs:n,slots:l,expose:b}){let t=K("ComboboxButton");b({el:t.buttonRef,$el:t.buttonRef});function o(r){t.disabled.value||(t.comboboxState.value===0?t.closeCombobox():(r.preventDefault(),t.openCombobox()),L(()=>{var f;return(f=I(t.inputRef))==null?void 0:f.focus({preventScroll:!0})}))}function v(r){switch(r.key){case V.ArrowDown:r.preventDefault(),r.stopPropagation(),t.comboboxState.value===1&&t.openCombobox(),L(()=>{var f;return(f=t.inputRef.value)==null?void 0:f.focus({preventScroll:!0})});return;case V.ArrowUp:r.preventDefault(),r.stopPropagation(),t.comboboxState.value===1&&(t.openCombobox(),L(()=>{t.value.value||t.goToOption(w.Last)})),L(()=>{var f;return(f=t.inputRef.value)==null?void 0:f.focus({preventScroll:!0})});return;case V.Escape:if(t.comboboxState.value!==0)return;r.preventDefault(),t.optionsRef.value&&!t.optionsPropsRef.value.static&&r.stopPropagation(),t.closeCombobox(),L(()=>{var f;return(f=t.inputRef.value)==null?void 0:f.focus({preventScroll:!0})});return}}let i=ie(g(()=>({as:e.as,type:n.type})),t.buttonRef);return()=>{var r,f;let x={open:t.comboboxState.value===0,disabled:t.disabled.value,value:t.value.value},{id:S,...T}=e,E={ref:t.buttonRef,id:S,type:i.value,tabindex:"-1","aria-haspopup":"listbox","aria-controls":(r=I(t.optionsRef))==null?void 0:r.id,"aria-expanded":t.comboboxState.value===0,"aria-labelledby":t.labelRef.value?[(f=I(t.labelRef))==null?void 0:f.id,S].join(" "):void 0,disabled:t.disabled.value===!0?!0:void 0,onKeydown:v,onClick:o};return M({ourProps:E,theirProps:T,slot:x,attrs:n,slots:l,name:"ComboboxButton"})}}}),Ne=$({name:"ComboboxInput",props:{as:{type:[Object,String],default:"input"},static:{type:Boolean,default:!1},unmount:{type:Boolean,default:!0},displayValue:{type:Function},defaultValue:{type:String,default:void 0},id:{type:String,default:()=>`headlessui-combobox-input-${_()}`}},emits:{change:e=>!0},setup(e,{emit:n,attrs:l,slots:b,expose:t}){let o=K("ComboboxInput"),v=g(()=>Z(I(o.inputRef))),i={value:!1};t({el:o.inputRef,$el:o.inputRef});function r(){o.change(null);let a=I(o.optionsRef);a&&(a.scrollTop=0),o.goToOption(w.Nothing)}let f=g(()=>{var a;let R=o.value.value;return I(o.inputRef)?typeof e.displayValue<"u"&&R!==void 0?(a=e.displayValue(R))!=null?a:"":typeof R=="string"?R:"":""});Y(()=>{W([f,o.comboboxState,v],([a,R],[y,F])=>{if(i.value)return;let C=I(o.inputRef);C&&((F===0&&R===1||a!==y)&&(C.value=a),requestAnimationFrame(()=>{var d;if(i.value||!C||((d=v.value)==null?void 0:d.activeElement)!==C)return;let{selectionStart:D,selectionEnd:N}=C;Math.abs((N!=null?N:0)-(D!=null?D:0))===0&&D===0&&C.setSelectionRange(C.value.length,C.value.length)}))},{immediate:!0}),W([o.comboboxState],([a],[R])=>{if(a===0&&R===1){if(i.value)return;let y=I(o.inputRef);if(!y)return;let F=y.value,{selectionStart:C,selectionEnd:d,selectionDirection:D}=y;y.value="",y.value=F,D!==null?y.setSelectionRange(C,d,D):y.setSelectionRange(C,d)}})});let x=A(!1);function S(){x.value=!0}function T(){pe().nextFrame(()=>{x.value=!1})}function E(a){switch(i.value=!0,a.key){case V.Enter:if(i.value=!1,o.comboboxState.value!==0||x.value)return;if(a.preventDefault(),a.stopPropagation(),o.activeOptionIndex.value===null){o.closeCombobox();return}o.selectActiveOption(),o.mode.value===0&&o.closeCombobox();break;case V.ArrowDown:return i.value=!1,a.preventDefault(),a.stopPropagation(),j(o.comboboxState.value,{[0]:()=>o.goToOption(w.Next),[1]:()=>o.openCombobox()});case V.ArrowUp:return i.value=!1,a.preventDefault(),a.stopPropagation(),j(o.comboboxState.value,{[0]:()=>o.goToOption(w.Previous),[1]:()=>{o.openCombobox(),L(()=>{o.value.value||o.goToOption(w.Last)})}});case V.Home:if(a.shiftKey)break;return i.value=!1,a.preventDefault(),a.stopPropagation(),o.goToOption(w.First);case V.PageUp:return i.value=!1,a.preventDefault(),a.stopPropagation(),o.goToOption(w.First);case V.End:if(a.shiftKey)break;return i.value=!1,a.preventDefault(),a.stopPropagation(),o.goToOption(w.Last);case V.PageDown:return i.value=!1,a.preventDefault(),a.stopPropagation(),o.goToOption(w.Last);case V.Escape:if(i.value=!1,o.comboboxState.value!==0)return;a.preventDefault(),o.optionsRef.value&&!o.optionsPropsRef.value.static&&a.stopPropagation(),o.nullable.value&&o.mode.value===0&&o.value.value===null&&r(),o.closeCombobox();break;case V.Tab:if(i.value=!1,o.comboboxState.value!==0)return;o.mode.value===0&&o.selectActiveOption(),o.closeCombobox();break}}function B(a){n("change",a),o.nullable.value&&o.mode.value===0&&a.target.value===""&&r(),o.openCombobox()}function k(){i.value=!1}let h=g(()=>{var a,R,y,F;return(F=(y=(R=e.defaultValue)!=null?R:o.defaultValue.value!==void 0?(a=e.displayValue)==null?void 0:a.call(e,o.defaultValue.value):null)!=null?y:o.defaultValue.value)!=null?F:""});return()=>{var a,R,y,F,C,d;let D={open:o.comboboxState.value===0},{id:N,displayValue:u,onChange:p,...c}=e,s={"aria-controls":(a=o.optionsRef.value)==null?void 0:a.id,"aria-expanded":o.comboboxState.value===0,"aria-activedescendant":o.activeOptionIndex.value===null||(R=o.options.value[o.activeOptionIndex.value])==null?void 0:R.id,"aria-labelledby":(C=(y=I(o.labelRef))==null?void 0:y.id)!=null?C:(F=I(o.buttonRef))==null?void 0:F.id,"aria-autocomplete":"list",id:N,onCompositionstart:S,onCompositionend:T,onKeydown:E,onInput:B,onBlur:k,role:"combobox",type:(d=l.type)!=null?d:"text",tabIndex:0,ref:o.inputRef,defaultValue:h.value,disabled:o.disabled.value===!0?!0:void 0};return M({ourProps:s,theirProps:c,slot:D,attrs:l,slots:b,features:z.RenderStrategy|z.Static,name:"ComboboxInput"})}}}),je=$({name:"ComboboxOptions",props:{as:{type:[Object,String],default:"ul"},static:{type:Boolean,default:!1},unmount:{type:Boolean,default:!0},hold:{type:[Boolean],default:!1}},setup(e,{attrs:n,slots:l,expose:b}){let t=K("ComboboxOptions"),o=`headlessui-combobox-options-${_()}`;b({el:t.optionsRef,$el:t.optionsRef}),H(()=>{t.optionsPropsRef.value.static=e.static}),H(()=>{t.optionsPropsRef.value.hold=e.hold});let v=ue(),i=g(()=>v!==null?(v.value&q.Open)===q.Open:t.comboboxState.value===0);return Re({container:g(()=>I(t.optionsRef)),enabled:g(()=>t.comboboxState.value===0),accept(r){return r.getAttribute("role")==="option"?NodeFilter.FILTER_REJECT:r.hasAttribute("role")?NodeFilter.FILTER_SKIP:NodeFilter.FILTER_ACCEPT},walk(r){r.setAttribute("role","none")}}),()=>{var r,f,x;let S={open:t.comboboxState.value===0},T={"aria-labelledby":(x=(r=I(t.labelRef))==null?void 0:r.id)!=null?x:(f=I(t.buttonRef))==null?void 0:f.id,id:o,ref:t.optionsRef,role:"listbox","aria-multiselectable":t.mode.value===1?!0:void 0},E=Q(e,["hold"]);return M({ourProps:T,theirProps:E,slot:S,attrs:n,slots:l,features:z.RenderStrategy|z.Static,visible:i.value,name:"ComboboxOptions"})}}}),Le=$({name:"ComboboxOption",props:{as:{type:[Object,String],default:"li"},value:{type:[Object,String,Number,Boolean]},disabled:{type:Boolean,default:!1}},setup(e,{slots:n,attrs:l,expose:b}){let t=K("ComboboxOption"),o=`headlessui-combobox-option-${_()}`,v=A(null);b({el:v,$el:v});let i=g(()=>t.activeOptionIndex.value!==null?t.options.value[t.activeOptionIndex.value].id===o:!1),r=g(()=>j(t.mode.value,{[0]:()=>t.compare(O(t.value.value),O(e.value)),[1]:()=>O(t.value.value).some(h=>t.compare(O(h),O(e.value)))})),f=g(()=>({disabled:e.disabled,value:e.value,domRef:v}));Y(()=>t.registerOption(o,f)),me(()=>t.unregisterOption(o)),H(()=>{t.comboboxState.value===0&&i.value&&t.activationTrigger.value!==0&&L(()=>{var h,a;return(a=(h=I(v))==null?void 0:h.scrollIntoView)==null?void 0:a.call(h,{block:"nearest"})})});function x(h){if(e.disabled)return h.preventDefault();t.selectOption(o),t.mode.value===0&&t.closeCombobox(),ce()||requestAnimationFrame(()=>{var a;return(a=I(t.inputRef))==null?void 0:a.focus()})}function S(){if(e.disabled)return t.goToOption(w.Nothing);t.goToOption(w.Specific,o)}let T=Ie();function E(h){T.update(h)}function B(h){T.wasMoved(h)&&(e.disabled||i.value||t.goToOption(w.Specific,o,0))}function k(h){T.wasMoved(h)&&(e.disabled||i.value&&(t.optionsPropsRef.value.hold||t.goToOption(w.Nothing)))}return()=>{let{disabled:h}=e,a={active:i.value,selected:r.value,disabled:h},R={id:o,ref:v,role:"option",tabIndex:h===!0?void 0:-1,"aria-disabled":h===!0?!0:void 0,"aria-selected":r.value,disabled:void 0,onClick:x,onFocus:S,onPointerenter:E,onMouseenter:E,onPointermove:B,onMousemove:B,onPointerleave:k,onMouseleave:k};return M({ourProps:R,theirProps:e,slot:a,attrs:l,slots:n,name:"ComboboxOption"})}}});function Me(e,n){return ge(),Oe("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[X("path",{d:"M6.5 9a2.5 2.5 0 115 0 2.5 2.5 0 01-5 0z"}),X("path",{"fill-rule":"evenodd",d:"M10 18a8 8 0 100-16 8 8 0 000 16zM9 5a4 4 0 102.248 7.309l1.472 1.471a.75.75 0 101.06-1.06l-1.471-1.472A4 4 0 009 5z","clip-rule":"evenodd"})])}export{Be as G,ke as J,Ne as Q,De as W,je as X,Le as Y,Me as r};

import{o as N,f as G,b as M,m as k,N as Ge,e as Ke,n as E,F as ge,l as Le,r as J,P as _e,d as Ll,t as ce,Q as Z,R as ke,h as F,k as h,B as x,H as Ce,j as xe,a as kl,u as wl}from"./app.4afcab37.js";function ae(e){return[null,void 0].indexOf(e)!==-1}function Pl(e,i,a){const{object:r,valueProp:n,mode:b}=Z(e),t=ke().proxy,d=a.iv,q=(y,I=!0)=>{d.value=c(y);const S=g(y);i.emit("change",S,t),I&&(i.emit("input",S),i.emit("update:modelValue",S))},g=y=>r.value||ae(y)?y:Array.isArray(y)?y.map(I=>I[n.value]):y[n.value],c=y=>ae(y)?b.value==="single"?{}:[]:y;return{update:q}}function Tl(e,i){const{value:a,modelValue:r,mode:n,valueProp:b}=Z(e),t=F(n.value!=="single"?[]:{}),d=r&&r.value!==void 0?r:a,q=h(()=>n.value==="single"?t.value[b.value]:t.value.map(c=>c[b.value])),g=h(()=>n.value!=="single"?t.value.map(c=>c[b.value]).join(","):t.value[b.value]);return{iv:t,internalValue:t,ev:d,externalValue:d,textValue:g,plainValue:q}}function ql(e,i,a){const{regex:r}=Z(e),n=ke().proxy,b=a.isOpen,t=a.open,d=F(null),q=F(null),g=()=>{d.value=""},c=S=>{d.value=S.target.value},y=S=>{if(r&&r.value){let w=r.value;typeof w=="string"&&(w=new RegExp(w)),S.key.match(w)||S.preventDefault()}},I=S=>{if(r&&r.value){let A=(S.clipboardData||window.clipboardData).getData("Text"),B=r.value;typeof B=="string"&&(B=new RegExp(B)),A.split("").every(m=>!!m.match(B))||S.preventDefault()}i.emit("paste",S,n)};return x(d,S=>{!b.value&&S&&t(),i.emit("search-change",S,n)}),{search:d,input:q,clearSearch:g,handleSearchInput:c,handleKeypress:y,handlePaste:I}}function El(e,i,a){const{groupSelect:r,mode:n,groups:b,disabledProp:t}=Z(e),d=F(null),q=c=>{c===void 0||c!==null&&c[t.value]||b.value&&c&&c.group&&(n.value==="single"||!r.value)||(d.value=c)};return{pointer:d,setPointer:q,clearPointer:()=>{q(null)}}}function Fe(e,i=!0){return i?String(e).toLowerCase().trim():String(e).toLowerCase().normalize("NFD").trim().replace(new RegExp(/æ/g),"ae").replace(new RegExp(/œ/g),"oe").replace(new RegExp(/ø/g),"o").replace(/\p{Diacritic}/gu,"")}function Cl(e){return Object.prototype.toString.call(e)==="[object Object]"}function Il(e,i){const a=i.slice().sort();return e.length===i.length&&e.slice().sort().every(function(r,n){return r===a[n]})}function Bl(e,i,a){const{options:r,mode:n,trackBy:b,limit:t,hideSelected:d,createTag:q,createOption:g,label:c,appendNewTag:y,appendNewOption:I,multipleLabel:S,object:w,loading:A,delay:B,resolveOnLoad:m,minChars:o,filterResults:C,clearOnSearch:V,clearOnSelect:z,valueProp:p,allowAbsent:H,groupLabel:W,canDeselect:j,max:X,strict:$,closeOnSelect:v,closeOnDeselect:L,groups:_,reverse:te,infinite:be,groupOptions:U,groupHideEmpty:we,groupSelect:P,onCreate:D,disabledProp:R,searchStart:re,searchFilter:me}=Z(e),Q=ke().proxy,T=a.iv,u=a.ev,f=a.search,Y=a.clearSearch,ne=a.update,el=a.pointer,de=a.clearPointer,ll=a.focus,al=a.deactivate,ve=a.close,tl=a.localize,ye=F([]),ie=F([]),ee=F(!1),Ie=F(null),Be=F(be.value&&t.value===-1?10:t.value),ze=h(()=>q.value||g.value||!1),nl=h(()=>y.value!==void 0?y.value:I.value!==void 0?I.value:!0),oe=h(()=>{if(_.value){let l=Pe.value||[],s=[];return l.forEach(O=>{Me(O[U.value]).forEach(K=>{s.push(Object.assign({},K,O[R.value]?{[R.value]:!0}:{}))})}),s}else{let l=Me(ie.value||[]);return ye.value.length&&(l=l.concat(ye.value)),l}}),We=h(()=>{let l=oe.value;return te.value&&(l=l.reverse()),fe.value.length&&(l=fe.value.concat(l)),Ve(l)}),Oe=h(()=>{let l=We.value;return Be.value>0&&(l=l.slice(0,Be.value)),l}),Pe=h(()=>{if(!_.value)return[];let l=[],s=ie.value||[];return ye.value.length&&l.push({[W.value]:" ",[U.value]:[...ye.value],__CREATE__:!0}),l.concat(s)}),ul=h(()=>{let l=[...Pe.value].map(s=>({...s}));return fe.value.length&&(l[0]&&l[0].__CREATE__?l[0][U.value]=[...fe.value,...l[0][U.value]]:l=[{[W.value]:" ",[U.value]:[...fe.value],__CREATE__:!0}].concat(l)),l}),Ue=h(()=>{if(!_.value)return[];let l=ul.value;return Ol((l||[]).map((s,O)=>{const K=Me(s[U.value]);return{...s,index:O,group:!0,[U.value]:Ve(K,!1).map(Se=>Object.assign({},Se,s[R.value]?{[R.value]:!0}:{})),__VISIBLE__:Ve(K).map(Se=>Object.assign({},Se,s[R.value]?{[R.value]:!0}:{}))}}))}),De=h(()=>{switch(n.value){case"single":return!ae(T.value[p.value]);case"multiple":case"tags":return!ae(T.value)&&T.value.length>0}}),sl=h(()=>S!==void 0&&S.value!==void 0?S.value(T.value,Q):T.value&&T.value.length>1?`${T.value.length} options selected`:"1 option selected"),rl=h(()=>!oe.value.length&&!ee.value&&!fe.value.length),il=h(()=>oe.value.length>0&&Oe.value.length==0&&(f.value&&_.value||!_.value)),fe=h(()=>ze.value===!1||!f.value?[]:bl(f.value)!==-1?[]:[{[p.value]:f.value,[le.value]:f.value,[c.value]:f.value,__CREATE__:!0}]),le=h(()=>b.value||c.value),ol=h(()=>{switch(n.value){case"single":return null;case"multiple":case"tags":return[]}}),cl=h(()=>A.value||ee.value),pe=l=>{switch(typeof l!="object"&&(l=se(l)),n.value){case"single":ne(l);break;case"multiple":case"tags":ne(T.value.concat(l));break}i.emit("select",Qe(l),l,Q)},he=l=>{switch(typeof l!="object"&&(l=se(l)),n.value){case"single":Xe();break;case"tags":case"multiple":ne(Array.isArray(l)?T.value.filter(s=>l.map(O=>O[p.value]).indexOf(s[p.value])===-1):T.value.filter(s=>s[p.value]!=l[p.value]));break}i.emit("deselect",Qe(l),l,Q)},Qe=l=>w.value?l:l[p.value],Je=l=>{he(l)},dl=(l,s)=>{if(s.button!==0){s.preventDefault();return}Je(l)},Xe=()=>{i.emit("clear",Q),ne(ol.value)},ue=l=>{if(l.group!==void 0)return n.value==="single"?!1:gl(l[U.value])&&l[U.value].length;switch(n.value){case"single":return!ae(T.value)&&T.value[p.value]==l[p.value];case"tags":case"multiple":return!ae(T.value)&&T.value.map(s=>s[p.value]).indexOf(l[p.value])!==-1}},Ae=l=>l[R.value]===!0,Re=()=>X===void 0||X.value===-1||!De.value&&X.value>0?!1:T.value.length>=X.value,vl=l=>{if(!Ae(l)){if(D&&D.value&&!ue(l)&&l.__CREATE__&&(l={...l},delete l.__CREATE__,l=D.value(l,Q),l instanceof Promise)){ee.value=!0,l.then(s=>{ee.value=!1,Ye(s)});return}Ye(l)}},Ye=l=>{switch(l.__CREATE__&&(l={...l},delete l.__CREATE__),n.value){case"single":if(l&&ue(l)){j.value&&he(l),L.value&&(de(),ve());return}l&&je(l),z.value&&Y(),v.value&&(de(),ve()),l&&pe(l);break;case"multiple":if(l&&ue(l)){he(l),L.value&&(de(),ve());return}if(Re()){i.emit("max",Q);return}l&&(je(l),pe(l)),z.value&&Y(),d.value&&de(),v.value&&ve();break;case"tags":if(l&&ue(l)){he(l),L.value&&(de(),ve());return}if(Re()){i.emit("max",Q);return}l&&je(l),z.value&&Y(),l&&pe(l),d.value&&de(),v.value&&ve();break}v.value||ll()},fl=l=>{if(!(Ae(l)||n.value==="single"||!P.value)){switch(n.value){case"multiple":case"tags":hl(l[U.value])?he(l[U.value]):pe(l[U.value].filter(s=>T.value.map(O=>O[p.value]).indexOf(s[p.value])===-1).filter(s=>!s[R.value]).filter((s,O)=>T.value.length+1+O<=X.value||X.value===-1));break}v.value&&al()}},je=l=>{se(l[p.value])===void 0&&ze.value&&(i.emit("tag",l[p.value],Q),i.emit("option",l[p.value],Q),i.emit("create",l[p.value],Q),nl.value&&yl(l),Y())},pl=()=>{n.value!=="single"&&pe(Oe.value.filter(l=>!l.disabled&&!ue(l)))},hl=l=>l.find(s=>!ue(s)&&!s[R.value])===void 0,gl=l=>l.find(s=>!ue(s))===void 0,se=l=>oe.value[oe.value.map(s=>String(s[p.value])).indexOf(String(l))],bl=(l,s=!0)=>oe.value.map(O=>parseInt(O[le.value])==O[le.value]?parseInt(O[le.value]):O[le.value]).indexOf(parseInt(l)==l?parseInt(l):l),ml=l=>["tags","multiple"].indexOf(n.value)!==-1&&d.value&&ue(l),yl=l=>{ye.value.push(l)},Ol=l=>we.value?l.filter(s=>f.value?s.__VISIBLE__.length:s[U.value].length):l.filter(s=>f.value?s.__VISIBLE__.length:!0),Ve=(l,s=!0)=>{let O=l;if(f.value&&C.value){let K=me.value;K||(K=(Se,oa)=>{let $e=Fe(tl(Se[le.value]),$.value);return re.value?$e.startsWith(Fe(f.value,$.value)):$e.indexOf(Fe(f.value,$.value))!==-1}),O=O.filter(K)}return d.value&&s&&(O=O.filter(K=>!ml(K))),O},Me=l=>{let s=l;return Cl(s)&&(s=Object.keys(s).map(O=>{let K=s[O];return{[p.value]:O,[le.value]:K,[c.value]:K}})),s=s.map(O=>typeof O=="object"?O:{[p.value]:O,[le.value]:O,[c.value]:O}),s},Te=()=>{ae(u.value)||(T.value=Ee(u.value))},qe=l=>(ee.value=!0,new Promise((s,O)=>{r.value(f.value,Q).then(K=>{ie.value=K||[],typeof l=="function"&&l(K),ee.value=!1}).catch(K=>{console.error(K),ie.value=[],ee.value=!1}).finally(()=>{s()})})),Ne=()=>{if(!!De.value)if(n.value==="single"){let l=se(T.value[p.value]);if(l!==void 0){let s=l[c.value];T.value[c.value]=s,w.value&&(u.value[c.value]=s)}}else T.value.forEach((l,s)=>{let O=se(T.value[s][p.value]);if(O!==void 0){let K=O[c.value];T.value[s][c.value]=K,w.value&&(u.value[s][c.value]=K)}})},Sl=l=>{qe(l)},Ee=l=>ae(l)?n.value==="single"?{}:[]:w.value?l:n.value==="single"?se(l)||(H.value?{[c.value]:l,[p.value]:l,[le.value]:l}:{}):l.filter(s=>!!se(s)||H.value).map(s=>se(s)||{[c.value]:s,[p.value]:s,[le.value]:s}),Ze=()=>{Ie.value=x(f,l=>{l.length<o.value||!l&&o.value!==0||(ee.value=!0,V.value&&(ie.value=[]),setTimeout(()=>{l==f.value&&r.value(f.value,Q).then(s=>{(l==f.value||!f.value)&&(ie.value=s,el.value=Oe.value.filter(O=>O[R.value]!==!0)[0]||null,ee.value=!1)}).catch(s=>{console.error(s)})},B.value))},{flush:"sync"})};if(n.value!=="single"&&!ae(u.value)&&!Array.isArray(u.value))throw new Error(`v-model must be an array when using "${n.value}" mode`);return r&&typeof r.value=="function"?m.value?qe(Te):w.value==!0&&Te():(ie.value=r.value,Te()),B.value>-1&&Ze(),x(B,(l,s)=>{Ie.value&&Ie.value(),l>=0&&Ze()}),x(u,l=>{if(ae(l)){ne(Ee(l),!1);return}switch(n.value){case"single":(w.value?l[p.value]!=T.value[p.value]:l!=T.value[p.value])&&ne(Ee(l),!1);break;case"multiple":case"tags":Il(w.value?l.map(s=>s[p.value]):l,T.value.map(s=>s[p.value]))||ne(Ee(l),!1);break}},{deep:!0}),x(r,(l,s)=>{typeof e.options=="function"?m.value&&(!s||l&&l.toString()!==s.toString())&&qe():(ie.value=e.options,Object.keys(T.value).length||Te(),Ne())}),x(c,Ne),{pfo:We,fo:Oe,filteredOptions:Oe,hasSelected:De,multipleLabelText:sl,eo:oe,extendedOptions:oe,eg:Pe,extendedGroups:Pe,fg:Ue,filteredGroups:Ue,noOptions:rl,noResults:il,resolving:ee,busy:cl,offset:Be,select:pe,deselect:he,remove:Je,selectAll:pl,clear:Xe,isSelected:ue,isDisabled:Ae,isMax:Re,getOption:se,handleOptionClick:vl,handleGroupClick:fl,handleTagRemove:dl,refreshOptions:Sl,resolveOptions:qe,refreshLabels:Ne}}function Dl(e,i,a){const{valueProp:r,showOptions:n,searchable:b,groupLabel:t,groups:d,mode:q,groupSelect:g,disabledProp:c,groupOptions:y}=Z(e),I=a.fo,S=a.fg,w=a.handleOptionClick,A=a.handleGroupClick,B=a.search,m=a.pointer,o=a.setPointer,C=a.clearPointer,V=a.multiselect,z=a.isOpen,p=h(()=>I.value.filter(u=>!u[c.value])),H=h(()=>S.value.filter(u=>!u[c.value])),W=h(()=>q.value!=="single"&&g.value),j=h(()=>m.value&&m.value.group),X=h(()=>Q(m.value)),$=h(()=>{const u=j.value?m.value:Q(m.value),f=H.value.map(ne=>ne[t.value]).indexOf(u[t.value]);let Y=H.value[f-1];return Y===void 0&&(Y=L.value),Y}),v=h(()=>{let u=H.value.map(f=>f.label).indexOf(j.value?m.value[t.value]:Q(m.value)[t.value])+1;return H.value.length<=u&&(u=0),H.value[u]}),L=h(()=>[...H.value].slice(-1)[0]),_=h(()=>m.value.__VISIBLE__.filter(u=>!u[c.value])[0]),te=h(()=>{const u=X.value.__VISIBLE__.filter(f=>!f[c.value]);return u[u.map(f=>f[r.value]).indexOf(m.value[r.value])-1]}),be=h(()=>{const u=Q(m.value).__VISIBLE__.filter(f=>!f[c.value]);return u[u.map(f=>f[r.value]).indexOf(m.value[r.value])+1]}),U=h(()=>[...$.value.__VISIBLE__.filter(u=>!u[c.value])].slice(-1)[0]),we=h(()=>[...L.value.__VISIBLE__.filter(u=>!u[c.value])].slice(-1)[0]),P=u=>!!m.value&&(!u.group&&m.value[r.value]===u[r.value]||u.group!==void 0&&m.value[t.value]===u[t.value])?!0:void 0,D=()=>{o(p.value[0]||null)},R=()=>{!m.value||m.value[c.value]===!0||(j.value?A(m.value):w(m.value))},re=()=>{if(m.value===null)o((d.value&&W.value?H.value[0].__CREATE__?p.value[0]:H.value[0]:p.value[0])||null);else if(d.value&&W.value){let u=j.value?_.value:be.value;u===void 0&&(u=v.value,u.__CREATE__&&(u=u[y.value][0])),o(u||null)}else{let u=p.value.map(f=>f[r.value]).indexOf(m.value[r.value])+1;p.value.length<=u&&(u=0),o(p.value[u]||null)}Ce(()=>{T()})},me=()=>{if(m.value===null){let u=p.value[p.value.length-1];d.value&&W.value&&(u=we.value,u===void 0&&(u=L.value)),o(u||null)}else if(d.value&&W.value){let u=j.value?U.value:te.value;u===void 0&&(u=j.value?$.value:X.value,u.__CREATE__&&(u=U.value,u===void 0&&(u=$.value))),o(u||null)}else{let u=p.value.map(f=>f[r.value]).indexOf(m.value[r.value])-1;u<0&&(u=p.value.length-1),o(p.value[u]||null)}Ce(()=>{T()})},Q=u=>H.value.find(f=>f.__VISIBLE__.map(Y=>Y[r.value]).indexOf(u[r.value])!==-1),T=()=>{let u=V.value.querySelector("[data-pointed]");if(!u)return;let f=u.parentElement.parentElement;d.value&&(f=j.value?u.parentElement.parentElement.parentElement:u.parentElement.parentElement.parentElement.parentElement),u.offsetTop+u.offsetHeight>f.clientHeight+f.scrollTop&&(f.scrollTop=u.offsetTop+u.offsetHeight-f.clientHeight),u.offsetTop<f.scrollTop&&(f.scrollTop=u.offsetTop)};return x(B,u=>{b.value&&(u.length&&n.value?D():C())}),x(z,u=>{if(u){let f=V.value.querySelectorAll("[data-selected]")[0];if(!f)return;let Y=f.parentElement.parentElement;Ce(()=>{Y.scrollTop>0||(Y.scrollTop=f.offsetTop)})}}),{pointer:m,canPointGroups:W,isPointed:P,setPointerFirst:D,selectPointer:R,forwardPointer:re,backwardPointer:me}}function Al(e,i,a){const{disabled:r}=Z(e),n=ke().proxy,b=F(!1);return{isOpen:b,open:()=>{b.value||r.value||(b.value=!0,i.emit("open",n))},close:()=>{!b.value||(b.value=!1,i.emit("close",n))}}}function Rl(e,i,a){const{searchable:r,disabled:n,clearOnBlur:b}=Z(e),t=a.input,d=a.open,q=a.close,g=a.clearSearch,c=a.isOpen,y=F(null),I=F(null),S=F(null),w=F(!1),A=F(!1),B=h(()=>r.value||n.value?-1:0),m=()=>{r.value&&t.value.blur(),I.value.blur()},o=()=>{r.value&&!n.value&&t.value.focus()},C=(j=!0)=>{n.value||(w.value=!0,j&&d())},V=()=>{w.value=!1,setTimeout(()=>{w.value||(q(),b.value&&g())},1)};return{multiselect:y,wrapper:I,tags:S,tabindex:B,isActive:w,mouseClicked:A,blur:m,focus:o,activate:C,deactivate:V,handleFocusIn:j=>{j.target.closest("[data-tags]")&&j.target.nodeName!=="INPUT"||j.target.closest("[data-clear]")||C(A.value)},handleFocusOut:()=>{V()},handleCaretClick:()=>{V(),m()},handleMousedown:j=>{A.value=!0,c.value&&(j.target.isEqualNode(I.value)||j.target.isEqualNode(S.value))?setTimeout(()=>{V()},0):document.activeElement.isEqualNode(I.value)&&!c.value&&C(),setTimeout(()=>{A.value=!1},0)}}}function jl(e,i,a){const{mode:r,addTagOn:n,openDirection:b,searchable:t,showOptions:d,valueProp:q,groups:g,addOptionOn:c,createTag:y,createOption:I,reverse:S}=Z(e),w=ke().proxy,A=a.iv,B=a.update,m=a.search,o=a.setPointer,C=a.selectPointer,V=a.backwardPointer,z=a.forwardPointer,p=a.multiselect,H=a.wrapper,W=a.tags,j=a.isOpen,X=a.open,$=a.blur,v=a.fo,L=h(()=>y.value||I.value||!1),_=h(()=>n.value!==void 0?n.value:c.value!==void 0?c.value:["enter"]),te=()=>{r.value==="tags"&&!d.value&&L.value&&t.value&&!g.value&&o(v.value[v.value.map(P=>P[q.value]).indexOf(m.value)])},be=P=>{let D=P.length-1;for(;D>=0&&(P[D].remove===!1||P[D].disabled);)D--;return D<0||P.splice(D,1),P};return{handleKeydown:P=>{i.emit("keydown",P,w);let D,R;switch(["ArrowLeft","ArrowRight","Enter"].indexOf(P.key)!==-1&&r.value==="tags"&&(D=[...p.value.querySelectorAll("[data-tags] > *")].filter(re=>re!==W.value),R=D.findIndex(re=>re===document.activeElement)),P.key){case"Backspace":if(r.value==="single"||t.value&&[null,""].indexOf(m.value)===-1||A.value.length===0)return;B(be([...A.value]));break;case"Enter":if(P.preventDefault(),P.keyCode===229)return;if(R!==-1&&R!==void 0){B([...A.value].filter((re,me)=>me!==R)),R===D.length-1&&(D.length-1?D[D.length-2].focus():t.value?W.value.querySelector("input").focus():H.value.focus());return}if(_.value.indexOf("enter")===-1&&L.value)return;te(),C();break;case" ":if(!L.value&&!t.value){P.preventDefault(),te(),C();return}if(!L.value)return!1;if(_.value.indexOf("space")===-1&&L.value)return;P.preventDefault(),te(),C();break;case"Tab":case";":case",":if(_.value.indexOf(P.key.toLowerCase())===-1||!L.value)return;te(),C(),P.preventDefault();break;case"Escape":$();break;case"ArrowUp":if(P.preventDefault(),!d.value)return;j.value||X(),V();break;case"ArrowDown":if(P.preventDefault(),!d.value)return;j.value||X(),z();break;case"ArrowLeft":if(t.value&&W.value&&W.value.querySelector("input").selectionStart||P.shiftKey||r.value!=="tags"||!A.value||!A.value.length)return;P.preventDefault(),R===-1?D[D.length-1].focus():R>0&&D[R-1].focus();break;case"ArrowRight":if(R===-1||P.shiftKey||r.value!=="tags"||!A.value||!A.value.length)return;P.preventDefault(),D.length>R+1?D[R+1].focus():t.value?W.value.querySelector("input").focus():t.value||H.value.focus();break}},handleKeyup:P=>{i.emit("keyup",P,w)},preparePointer:te}}function Vl(e,i,a){const{classes:r,disabled:n,openDirection:b,showOptions:t}=Z(e),d=a.isOpen,q=a.isPointed,g=a.isSelected,c=a.isDisabled,y=a.isActive,I=a.canPointGroups,S=a.resolving,w=a.fo,A=h(()=>({container:"multiselect",containerDisabled:"is-disabled",containerOpen:"is-open",containerOpenTop:"is-open-top",containerActive:"is-active",wrapper:"multiselect-wrapper",singleLabel:"multiselect-single-label",singleLabelText:"multiselect-single-label-text",multipleLabel:"multiselect-multiple-label",search:"multiselect-search",tags:"multiselect-tags",tag:"multiselect-tag",tagDisabled:"is-disabled",tagRemove:"multiselect-tag-remove",tagRemoveIcon:"multiselect-tag-remove-icon",tagsSearchWrapper:"multiselect-tags-search-wrapper",tagsSearch:"multiselect-tags-search",tagsSearchCopy:"multiselect-tags-search-copy",placeholder:"multiselect-placeholder",caret:"multiselect-caret",caretOpen:"is-open",clear:"multiselect-clear",clearIcon:"multiselect-clear-icon",spinner:"multiselect-spinner",inifinite:"multiselect-inifite",inifiniteSpinner:"multiselect-inifite-spinner",dropdown:"multiselect-dropdown",dropdownTop:"is-top",dropdownHidden:"is-hidden",options:"multiselect-options",optionsTop:"is-top",group:"multiselect-group",groupLabel:"multiselect-group-label",groupLabelPointable:"is-pointable",groupLabelPointed:"is-pointed",groupLabelSelected:"is-selected",groupLabelDisabled:"is-disabled",groupLabelSelectedPointed:"is-selected is-pointed",groupLabelSelectedDisabled:"is-selected is-disabled",groupOptions:"multiselect-group-options",option:"multiselect-option",optionPointed:"is-pointed",optionSelected:"is-selected",optionDisabled:"is-disabled",optionSelectedPointed:"is-selected is-pointed",optionSelectedDisabled:"is-selected is-disabled",noOptions:"multiselect-no-options",noResults:"multiselect-no-results",fakeInput:"multiselect-fake-input",assist:"multiselect-assistive-text",spacer:"multiselect-spacer",...r.value})),B=h(()=>!!(d.value&&t.value&&(!S.value||S.value&&w.value.length)));return{classList:h(()=>{const o=A.value;return{container:[o.container].concat(n.value?o.containerDisabled:[]).concat(B.value&&b.value==="top"?o.containerOpenTop:[]).concat(B.value&&b.value!=="top"?o.containerOpen:[]).concat(y.value?o.containerActive:[]),wrapper:o.wrapper,spacer:o.spacer,singleLabel:o.singleLabel,singleLabelText:o.singleLabelText,multipleLabel:o.multipleLabel,search:o.search,tags:o.tags,tag:[o.tag].concat(n.value?o.tagDisabled:[]),tagDisabled:o.tagDisabled,tagRemove:o.tagRemove,tagRemoveIcon:o.tagRemoveIcon,tagsSearchWrapper:o.tagsSearchWrapper,tagsSearch:o.tagsSearch,tagsSearchCopy:o.tagsSearchCopy,placeholder:o.placeholder,caret:[o.caret].concat(d.value?o.caretOpen:[]),clear:o.clear,clearIcon:o.clearIcon,spinner:o.spinner,inifinite:o.inifinite,inifiniteSpinner:o.inifiniteSpinner,dropdown:[o.dropdown].concat(b.value==="top"?o.dropdownTop:[]).concat(!d.value||!t.value||!B.value?o.dropdownHidden:[]),options:[o.options].concat(b.value==="top"?o.optionsTop:[]),group:o.group,groupLabel:C=>{let V=[o.groupLabel];return q(C)?V.push(g(C)?o.groupLabelSelectedPointed:o.groupLabelPointed):g(C)&&I.value?V.push(c(C)?o.groupLabelSelectedDisabled:o.groupLabelSelected):c(C)&&V.push(o.groupLabelDisabled),I.value&&V.push(o.groupLabelPointable),V},groupOptions:o.groupOptions,option:(C,V)=>{let z=[o.option];return q(C)?z.push(g(C)?o.optionSelectedPointed:o.optionPointed):g(C)?z.push(c(C)?o.optionSelectedDisabled:o.optionSelected):(c(C)||V&&c(V))&&z.push(o.optionDisabled),z},noOptions:o.noOptions,noResults:o.noResults,assist:o.assist,fakeInput:o.fakeInput}}),showDropdown:B}}function Ml(e,i,a){const{limit:r,infinite:n}=Z(e),b=a.isOpen,t=a.offset,d=a.search,q=a.pfo,g=a.eo,c=F(null),y=F(null),I=h(()=>t.value<q.value.length),S=A=>{const{isIntersecting:B,target:m}=A[0];if(B){const o=m.offsetParent,C=o.scrollTop;t.value+=r.value==-1?10:r.value,Ce(()=>{o.scrollTop=C})}},w=()=>{b.value&&t.value<q.value.length?c.value.observe(y.value):!b.value&&c.value&&c.value.disconnect()};return x(b,()=>{!n.value||w()}),x(d,()=>{!n.value||(t.value=r.value,w())},{flush:"post"}),x(g,()=>{!n.value||w()},{immediate:!1,flush:"post"}),xe(()=>{window&&window.IntersectionObserver&&(c.value=new IntersectionObserver(S))}),{hasMore:I,infiniteLoader:y}}function Nl(e,i,a){const{placeholder:r,id:n,valueProp:b,label:t,mode:d,groupLabel:q,aria:g,searchable:c}=Z(e),y=a.pointer,I=a.iv,S=a.hasSelected,w=a.multipleLabelText,A=F(null),B=h(()=>{let v=[];return n&&n.value&&v.push(n.value),v.push("assist"),v.join("-")}),m=h(()=>{let v=[];return n&&n.value&&v.push(n.value),v.push("multiselect-options"),v.join("-")}),o=h(()=>{let v=[];if(n&&n.value&&v.push(n.value),y.value)return v.push(y.value.group?"multiselect-group":"multiselect-option"),v.push(y.value.group?y.value.index:y.value[b.value]),v.join("-")}),C=h(()=>r.value),V=h(()=>d.value!=="single"),z=h(()=>{let v="";return d.value==="single"&&S.value&&(v+=I.value[t.value]),d.value==="multiple"&&S.value&&(v+=w.value),d.value==="tags"&&S.value&&(v+=I.value.map(L=>L[t.value]).join(", ")),v}),p=h(()=>{let v={...g.value};return c.value&&(v["aria-labelledby"]=v["aria-labelledby"]?`${B.value} ${v["aria-labelledby"]}`:B.value,z.value&&v["aria-label"]&&(v["aria-label"]=`${z.value}, ${v["aria-label"]}`)),v}),H=v=>{let L=[];return n&&n.value&&L.push(n.value),L.push("multiselect-option"),L.push(v[b.value]),L.join("-")},W=v=>{let L=[];return n&&n.value&&L.push(n.value),L.push("multiselect-group"),L.push(v.index),L.join("-")},j=v=>{let L=[];return L.push(v),L.join(" ")},X=v=>{let L=[];return L.push(v),L.join(" ")},$=v=>`${v} \u274E`;return xe(()=>{if(n&&n.value&&document&&document.querySelector){let v=document.querySelector(`[for="${n.value}"]`);A.value=v?v.innerText:null}}),{arias:p,ariaLabel:z,ariaAssist:B,ariaControls:m,ariaPlaceholder:C,ariaMultiselectable:V,ariaActiveDescendant:o,ariaOptionId:H,ariaOptionLabel:j,ariaGroupId:W,ariaGroupLabel:X,ariaTagLabel:$}}function Gl(e,i,a){const{locale:r,fallbackLocale:n}=Z(e);return{localize:t=>!t||typeof t!="object"?t:t&&t[r.value]?t[r.value]:t&&r.value&&t[r.value.toUpperCase()]?t[r.value.toUpperCase()]:t&&t[n.value]?t[n.value]:t&&n.value&&t[n.value.toUpperCase()]?t[n.value.toUpperCase()]:t&&Object.keys(t)[0]?t[Object.keys(t)[0]]:""}}function Kl(e,i,a,r={}){return a.forEach(n=>{n&&(r={...r,...n(e,i,r)})}),r}var He={name:"Multiselect",emits:["paste","open","close","select","deselect","input","search-change","tag","option","update:modelValue","change","clear","keydown","keyup","max","create"],props:{value:{required:!1},modelValue:{required:!1},options:{type:[Array,Object,Function],required:!1,default:()=>[]},id:{type:[String,Number],required:!1},name:{type:[String,Number],required:!1,default:"multiselect"},disabled:{type:Boolean,required:!1,default:!1},label:{type:String,required:!1,default:"label"},trackBy:{type:String,required:!1,default:void 0},valueProp:{type:String,required:!1,default:"value"},placeholder:{type:String,required:!1,default:null},mode:{type:String,required:!1,default:"single"},searchable:{type:Boolean,required:!1,default:!1},limit:{type:Number,required:!1,default:-1},hideSelected:{type:Boolean,required:!1,default:!0},createTag:{type:Boolean,required:!1,default:void 0},createOption:{type:Boolean,required:!1,default:void 0},appendNewTag:{type:Boolean,required:!1,default:void 0},appendNewOption:{type:Boolean,required:!1,default:void 0},addTagOn:{type:Array,required:!1,default:void 0},addOptionOn:{type:Array,required:!1,default:void 0},caret:{type:Boolean,required:!1,default:!0},loading:{type:Boolean,required:!1,default:!1},noOptionsText:{type:[String,Object],required:!1,default:"The list is empty"},noResultsText:{type:[String,Object],required:!1,default:"No results found"},multipleLabel:{type:Function,required:!1},object:{type:Boolean,required:!1,default:!1},delay:{type:Number,required:!1,default:-1},minChars:{type:Number,required:!1,default:0},resolveOnLoad:{type:Boolean,required:!1,default:!0},filterResults:{type:Boolean,required:!1,default:!0},clearOnSearch:{type:Boolean,required:!1,default:!1},clearOnSelect:{type:Boolean,required:!1,default:!0},canDeselect:{type:Boolean,required:!1,default:!0},canClear:{type:Boolean,required:!1,default:!0},max:{type:Number,required:!1,default:-1},showOptions:{type:Boolean,required:!1,default:!0},required:{type:Boolean,required:!1,default:!1},openDirection:{type:String,required:!1,default:"bottom"},nativeSupport:{type:Boolean,required:!1,default:!1},classes:{type:Object,required:!1,default:()=>({})},strict:{type:Boolean,required:!1,default:!0},closeOnSelect:{type:Boolean,required:!1,default:!0},closeOnDeselect:{type:Boolean,required:!1,default:!1},autocomplete:{type:String,required:!1},groups:{type:Boolean,required:!1,default:!1},groupLabel:{type:String,required:!1,default:"label"},groupOptions:{type:String,required:!1,default:"options"},groupHideEmpty:{type:Boolean,required:!1,default:!1},groupSelect:{type:Boolean,required:!1,default:!0},inputType:{type:String,required:!1,default:"text"},attrs:{required:!1,type:Object,default:()=>({})},onCreate:{required:!1,type:Function},disabledProp:{type:String,required:!1,default:"disabled"},searchStart:{type:Boolean,required:!1,default:!1},reverse:{type:Boolean,required:!1,default:!1},regex:{type:[Object,String,RegExp],required:!1,default:void 0},rtl:{type:Boolean,required:!1,default:!1},infinite:{type:Boolean,required:!1,default:!1},aria:{required:!1,type:Object,default:()=>({})},clearOnBlur:{required:!1,type:Boolean,default:!0},locale:{required:!1,type:String,default:null},fallbackLocale:{required:!1,type:String,default:"en"},searchFilter:{required:!1,type:Function,default:null},allowAbsent:{required:!1,type:Boolean,default:!1}},setup(e,i){return Kl(e,i,[Gl,Tl,El,Al,ql,Pl,Rl,Bl,Ml,Dl,jl,Vl,Nl])}};const Fl=["id","dir"],Hl=["tabindex","aria-controls","aria-placeholder","aria-expanded","aria-activedescendant","aria-multiselectable","role"],zl=["type","modelValue","value","autocomplete","id","aria-controls","aria-placeholder","aria-expanded","aria-activedescendant","aria-multiselectable"],Wl=["onKeyup","aria-label"],Ul=["onClick"],Ql=["type","modelValue","value","id","autocomplete","aria-controls","aria-placeholder","aria-expanded","aria-activedescendant","aria-multiselectable"],Jl=["innerHTML"],Xl=["id"],Yl=["id","aria-label","aria-selected"],Zl=["data-pointed","onMouseenter","onClick"],$l=["innerHTML"],_l=["aria-label"],xl=["data-pointed","data-selected","onMouseenter","onClick","id","aria-selected","aria-label"],ea=["data-pointed","data-selected","onMouseenter","onClick","id","aria-selected","aria-label"],la=["innerHTML"],aa=["innerHTML"],ta=["value"],na=["name","value"],ua=["name","value"],sa=["id"];function ra(e,i,a,r,n,b){return N(),G("div",{ref:"multiselect",class:E(e.classList.container),id:a.searchable?void 0:a.id,dir:a.rtl?"rtl":void 0,onFocusin:i[10]||(i[10]=(...t)=>e.handleFocusIn&&e.handleFocusIn(...t)),onFocusout:i[11]||(i[11]=(...t)=>e.handleFocusOut&&e.handleFocusOut(...t)),onKeyup:i[12]||(i[12]=(...t)=>e.handleKeyup&&e.handleKeyup(...t)),onKeydown:i[13]||(i[13]=(...t)=>e.handleKeydown&&e.handleKeydown(...t))},[M("div",Ge({class:e.classList.wrapper,onMousedown:i[9]||(i[9]=(...t)=>e.handleMousedown&&e.handleMousedown(...t)),ref:"wrapper",tabindex:e.tabindex,"aria-controls":a.searchable?void 0:e.ariaControls,"aria-placeholder":a.searchable?void 0:e.ariaPlaceholder,"aria-expanded":a.searchable?void 0:e.isOpen,"aria-activedescendant":a.searchable?void 0:e.ariaActiveDescendant,"aria-multiselectable":a.searchable?void 0:e.ariaMultiselectable,role:a.searchable?void 0:"combobox"},a.searchable?{}:e.arias),[k(" Search "),a.mode!=="tags"&&a.searchable&&!a.disabled?(N(),G("input",Ge({key:0,type:a.inputType,modelValue:e.search,value:e.search,class:e.classList.search,autocomplete:a.autocomplete,id:a.searchable?a.id:void 0,onInput:i[0]||(i[0]=(...t)=>e.handleSearchInput&&e.handleSearchInput(...t)),onKeypress:i[1]||(i[1]=(...t)=>e.handleKeypress&&e.handleKeypress(...t)),onPaste:i[2]||(i[2]=Ke((...t)=>e.handlePaste&&e.handlePaste(...t),["stop"])),ref:"input","aria-controls":e.ariaControls,"aria-placeholder":e.ariaPlaceholder,"aria-expanded":e.isOpen,"aria-activedescendant":e.ariaActiveDescendant,"aria-multiselectable":e.ariaMultiselectable,role:"combobox"},{...a.attrs,...e.arias}),null,16,zl)):k("v-if",!0),k(" Tags (with search) "),a.mode=="tags"?(N(),G("div",{key:1,class:E(e.classList.tags),"data-tags":""},[(N(!0),G(ge,null,Le(e.iv,(t,d,q)=>J(e.$slots,"tag",{option:t,handleTagRemove:e.handleTagRemove,disabled:a.disabled},()=>[(N(),G("span",{class:E([e.classList.tag,t.disabled?e.classList.tagDisabled:null]),tabindex:"-1",onKeyup:_e(g=>e.handleTagRemove(t,g),["enter"]),key:q,"aria-label":e.ariaTagLabel(e.localize(t[a.label]))},[Ll(ce(e.localize(t[a.label]))+" ",1),!a.disabled&&!t.disabled?(N(),G("span",{key:0,class:E(e.classList.tagRemove),onClick:Ke(g=>e.handleTagRemove(t,g),["stop"])},[M("span",{class:E(e.classList.tagRemoveIcon)},null,2)],10,Ul)):k("v-if",!0)],42,Wl))])),256)),M("div",{class:E(e.classList.tagsSearchWrapper),ref:"tags"},[k(" Used for measuring search width "),M("span",{class:E(e.classList.tagsSearchCopy)},ce(e.search),3),k(" Actual search input "),a.searchable&&!a.disabled?(N(),G("input",Ge({key:0,type:a.inputType,modelValue:e.search,value:e.search,class:e.classList.tagsSearch,id:a.searchable?a.id:void 0,autocomplete:a.autocomplete,onInput:i[3]||(i[3]=(...t)=>e.handleSearchInput&&e.handleSearchInput(...t)),onKeypress:i[4]||(i[4]=(...t)=>e.handleKeypress&&e.handleKeypress(...t)),onPaste:i[5]||(i[5]=Ke((...t)=>e.handlePaste&&e.handlePaste(...t),["stop"])),ref:"input","aria-controls":e.ariaControls,"aria-placeholder":e.ariaPlaceholder,"aria-expanded":e.isOpen,"aria-activedescendant":e.ariaActiveDescendant,"aria-multiselectable":e.ariaMultiselectable,role:"combobox"},{...a.attrs,...e.arias}),null,16,Ql)):k("v-if",!0)],2)],2)):k("v-if",!0),k(" Single label "),a.mode=="single"&&e.hasSelected&&!e.search&&e.iv?J(e.$slots,"singlelabel",{key:2,value:e.iv},()=>[M("div",{class:E(e.classList.singleLabel)},[M("span",{class:E(e.classList.singleLabelText)},ce(e.localize(e.iv[a.label])),3)],2)]):k("v-if",!0),k(" Multiple label "),a.mode=="multiple"&&e.hasSelected&&!e.search?J(e.$slots,"multiplelabel",{key:3,values:e.iv},()=>[M("div",{class:E(e.classList.multipleLabel),innerHTML:e.multipleLabelText},null,10,Jl)]):k("v-if",!0),k(" Placeholder "),a.placeholder&&!e.hasSelected&&!e.search?J(e.$slots,"placeholder",{key:4},()=>[M("div",{class:E(e.classList.placeholder),"aria-hidden":"true"},ce(a.placeholder),3)]):k("v-if",!0),k(" Spinner "),a.loading||e.resolving?J(e.$slots,"spinner",{key:5},()=>[M("span",{class:E(e.classList.spinner),"aria-hidden":"true"},null,2)]):k("v-if",!0),k(" Clear "),e.hasSelected&&!a.disabled&&a.canClear&&!e.busy?J(e.$slots,"clear",{key:6,clear:e.clear},()=>[M("span",{"aria-hidden":"true",tabindex:"0",role:"button","data-clear":"","aria-roledescription":"\u274E",class:E(e.classList.clear),onClick:i[6]||(i[6]=(...t)=>e.clear&&e.clear(...t)),onKeyup:i[7]||(i[7]=_e((...t)=>e.clear&&e.clear(...t),["enter"]))},[M("span",{class:E(e.classList.clearIcon)},null,2)],34)]):k("v-if",!0),k(" Caret "),a.caret&&a.showOptions?J(e.$slots,"caret",{key:7},()=>[M("span",{class:E(e.classList.caret),onClick:i[8]||(i[8]=(...t)=>e.handleCaretClick&&e.handleCaretClick(...t)),"aria-hidden":"true"},null,2)]):k("v-if",!0)],16,Hl),k(" Options "),M("div",{class:E(e.classList.dropdown),tabindex:"-1"},[J(e.$slots,"beforelist",{options:e.fo}),M("ul",{class:E(e.classList.options),id:e.ariaControls,role:"listbox"},[a.groups?(N(!0),G(ge,{key:0},Le(e.fg,(t,d,q)=>(N(),G("li",{class:E(e.classList.group),key:q,id:e.ariaGroupId(t),"aria-label":e.ariaGroupLabel(e.localize(t[a.groupLabel])),"aria-selected":e.isSelected(t),role:"option"},[t.__CREATE__?k("v-if",!0):(N(),G("div",{key:0,class:E(e.classList.groupLabel(t)),"data-pointed":e.isPointed(t),onMouseenter:g=>e.setPointer(t,d),onClick:g=>e.handleGroupClick(t)},[J(e.$slots,"grouplabel",{group:t,isSelected:e.isSelected,isPointed:e.isPointed},()=>[M("span",{innerHTML:e.localize(t[a.groupLabel])},null,8,$l)])],42,Zl)),M("ul",{class:E(e.classList.groupOptions),"aria-label":e.ariaGroupLabel(e.localize(t[a.groupLabel])),role:"group"},[(N(!0),G(ge,null,Le(t.__VISIBLE__,(g,c,y)=>(N(),G("li",{class:E(e.classList.option(g,t)),"data-pointed":e.isPointed(g),"data-selected":e.isSelected(g)||void 0,key:y,onMouseenter:I=>e.setPointer(g),onClick:I=>e.handleOptionClick(g),id:e.ariaOptionId(g),"aria-selected":e.isSelected(g),"aria-label":e.ariaOptionLabel(e.localize(g[a.label])),role:"option"},[J(e.$slots,"option",{option:g,isSelected:e.isSelected,isPointed:e.isPointed,search:e.search},()=>[M("span",null,ce(e.localize(g[a.label])),1)])],42,xl))),128))],10,_l)],10,Yl))),128)):(N(!0),G(ge,{key:1},Le(e.fo,(t,d,q)=>(N(),G("li",{class:E(e.classList.option(t)),"data-pointed":e.isPointed(t),"data-selected":e.isSelected(t)||void 0,key:q,onMouseenter:g=>e.setPointer(t),onClick:g=>e.handleOptionClick(t),id:e.ariaOptionId(t),"aria-selected":e.isSelected(t),"aria-label":e.ariaOptionLabel(e.localize(t[a.label])),role:"option"},[J(e.$slots,"option",{option:t,isSelected:e.isSelected,isPointed:e.isPointed,search:e.search},()=>[M("span",null,ce(e.localize(t[a.label])),1)])],42,ea))),128))],10,Xl),e.noOptions?J(e.$slots,"nooptions",{key:0},()=>[M("div",{class:E(e.classList.noOptions),innerHTML:e.localize(a.noOptionsText)},null,10,la)]):k("v-if",!0),e.noResults?J(e.$slots,"noresults",{key:1},()=>[M("div",{class:E(e.classList.noResults),innerHTML:e.localize(a.noResultsText)},null,10,aa)]):k("v-if",!0),a.infinite&&e.hasMore?(N(),G("div",{key:2,class:E(e.classList.inifinite),ref:"infiniteLoader"},[J(e.$slots,"infinite",{},()=>[M("span",{class:E(e.classList.inifiniteSpinner)},null,2)])],2)):k("v-if",!0),J(e.$slots,"afterlist",{options:e.fo})],2),k(" Hacky input element to show HTML5 required warning "),a.required?(N(),G("input",{key:0,class:E(e.classList.fakeInput),tabindex:"-1",value:e.textValue,required:""},null,10,ta)):k("v-if",!0),k(" Native input support "),a.nativeSupport?(N(),G(ge,{key:1},[a.mode=="single"?(N(),G("input",{key:0,type:"hidden",name:a.name,value:e.plainValue!==void 0?e.plainValue:""},null,8,na)):(N(!0),G(ge,{key:1},Le(e.plainValue,(t,d)=>(N(),G("input",{type:"hidden",name:`${a.name}[]`,value:t,key:d},null,8,ua))),128))],64)):k("v-if",!0),k(" Screen reader assistive text "),a.searchable&&e.hasSelected?(N(),G("div",{key:2,class:E(e.classList.assist),id:e.ariaAssist,"aria-hidden":"true"},ce(e.ariaLabel),11,sa)):k("v-if",!0),k(" Create height for empty input "),M("div",{class:E(e.classList.spacer)},null,2)],42,Fl)}He.render=ra;He.__file="src/Multiselect.vue";const ia={class:"overflow-visible"},da={__name:"MultiSelect",props:{canClear:Boolean,clearOnBlur:{type:[Boolean,String],default:!0},openDirection:{type:String,default:"bottom"},label:String,mode:String,modelValue:[Array,Object,String,Number],options:[Array,Object,String],placeholder:String,trackBy:String,valueProp:String,required:{type:[Boolean,String],default:!1}},emits:["update:modelValue","selected"],setup(e,{emit:i}){const a=e,r=F([]);function n(t){a.mode==="tags"?(r.value.push(t),i("update:modelValue",r.value)):i("update:modelValue",t),i("selected")}function b(t){a.mode==="tags"&&(r.value=r.value.filter(d=>d.id!=t.id),i("update:modelValue",r.value))}return(t,d)=>(N(),G("div",ia,[kl(wl(He),{modelValue:e.modelValue,canClear:e.canClear,canDeselect:!1,label:e.label,mode:e.mode,object:!0,options:e.options,placeholder:e.placeholder,required:e.required,searchable:!0,valueProp:e.valueProp,onSelect:n,onDeselect:b,clearOnBlur:e.clearOnBlur,openDirection:e.openDirection},null,8,["modelValue","canClear","label","mode","options","placeholder","required","valueProp","clearOnBlur","openDirection"])]))}};export{da as _};

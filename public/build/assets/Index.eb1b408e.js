import{_ as K}from"./Authenticated.c60e44a2.js";import{_ as c}from"./Button.a4bc7dc6.js";import T from"./Form.885c888b.js";import{_ as U,r as D,a as O,T as P,b as A,c as E,d as b}from"./TableHeadSort.31b87d59.js";import{_ as H}from"./MultiSelect.ab19e96f.js";import{i as m,j as R,o as i,g as x,a as t,b as d,w as o,F as B,H as J,d as e,t as f,m as q,p as w,c as S,f as g,J as k}from"./app.7e392996.js";import{r as z,a as G}from"./PlusIcon.f1ba9af9.js";import{r as Q}from"./TrashIcon.d0e50e3c.js";import"./open-closed.dc611207.js";import"./use-resolve-button-type.3a66aace.js";import"./RectangleStackIcon.7b70b060.js";import"./FormInput.c9fc7f3a.js";import"./Modal.7bb3d2e4.js";import"./ArrowUturnLeftIcon.52ddfe23.js";import"./CheckCircleIcon.e8e6b0bc.js";import"./_plugin-vue_export-helper.cdc0426e.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.1e831869.js";const W=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (UOM) ",-1),X={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},Y={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Z={class:"flex justify-end"},ee=e("span",null," Create ",-1),te={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se=g(" Name "),oe={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ae={class:"mt-3"},ne={class:"flex space-x-1"},le=e("span",null," Search ",-1),re=e("span",null," Reset ",-1),ie={class:"flex flex-col space-y-2"},de={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ue=e("span",null,"Showing",-1),ce={class:"font-medium"},me=e("span",null,"to",-1),fe={class:"font-medium"},ge=e("span",null,"of",-1),pe={class:"font-medium"},xe=e("span",null,"results",-1),_e={class:"mt-6 flex flex-col"},he={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ve={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ye={class:"min-w-full border-separate",style:{"border-spacing":"0"}},be={class:"bg-gray-100"},we={class:"divide-x divide-gray-200"},ke=g(" # "),Ce=g(" Name "),$e={class:"bg-white"},Pe={class:"flex justify-center space-x-1"},Be=e("span",null," Edit ",-1),Se=e("span",null," Delete ",-1),Ve={key:0},Ne=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Me=[Ne],Qe={__name:"Index",props:{uoms:Object},setup(a){const n=m({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),u=m(!1),_=m(),h=m(""),v=m([]);R(()=>{v.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],n.value.numberPerPage=v.value[0]});function V(){h.value="create",_.value=null,u.value=!0}function N(r){!confirm("Are you sure to delete "+r.name+"?")||k.Inertia.delete("/uoms/"+r.id)}function M(r){h.value="update",_.value=r,u.value=!0}function y(){k.Inertia.get("/uoms",{...n.value,numberPerPage:n.value.numberPerPage.id},{preserveState:!0,replace:!0})}function I(){k.Inertia.get("/uoms")}function L(r){n.value.sortKey=r,n.value.sortBy=!n.value.sortBy,y()}function j(){u.value=!1}return(r,s)=>(i(),x(B,null,[t(d(J),{title:"UOM"}),t(K,null,{header:o(()=>[W]),default:o(()=>{var C,$;return[e("div",X,[e("div",Y,[e("div",Z,[t(c,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[0]||(s[0]=l=>V())},{default:o(()=>[t(d(z),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})]),e("div",te,[t(U,{placeholderStr:"Name",modelValue:n.value.name,"onUpdate:modelValue":s[1]||(s[1]=l=>n.value.name=l)},{default:o(()=>[se]),_:1},8,["modelValue"])]),e("div",oe,[e("div",ae,[e("div",ne,[t(c,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[2]||(s[2]=l=>y())},{default:o(()=>[t(d(D),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1}),t(c,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[3]||(s[3]=l=>I())},{default:o(()=>[t(d(O),{class:"h-4 w-4","aria-hidden":"true"}),re]),_:1})])]),e("div",ie,[e("p",de,[ue,e("span",ce,f((C=a.uoms.meta.from)!=null?C:0),1),me,e("span",fe,f(($=a.uoms.meta.to)!=null?$:0),1),ge,e("span",pe,f(a.uoms.meta.total),1),xe]),t(H,{modelValue:n.value.numberPerPage,"onUpdate:modelValue":s[4]||(s[4]=l=>n.value.numberPerPage=l),options:v.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:y},null,8,["modelValue","options"])])])]),e("div",_e,[e("div",he,[e("div",ve,[e("table",ye,[e("thead",be,[e("tr",we,[t(P,null,{default:o(()=>[ke]),_:1}),t(A,{modelName:"name",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:s[5]||(s[5]=l=>L("name"))},{default:o(()=>[Ce]),_:1},8,["sortKey","sortBy"]),t(P)])]),e("tbody",$e,[(i(!0),x(B,null,q(a.uoms.data,(l,p)=>(i(),x("tr",{key:l.id,class:"divide-x divide-gray-200"},[t(b,{currentIndex:p,totalLength:a.uoms.length,inputClass:"text-center"},{default:o(()=>[g(f(a.uoms.meta.from+p),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:p,totalLength:a.uoms.length,inputClass:"text-left"},{default:o(()=>[g(f(l.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:p,totalLength:a.uoms.length,inputClass:"text-center"},{default:o(()=>[e("div",Pe,[t(c,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:F=>M(l)},{default:o(()=>[t(d(G),{class:"w-4 h-4"}),Be]),_:2},1032,["onClick"]),t(c,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:F=>N(l)},{default:o(()=>[t(d(Q),{class:"w-4 h-4"}),Se]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),a.uoms.data.length?w("",!0):(i(),x("tr",Ve,Me))])]),a.uoms.data.length?(i(),S(E,{key:0,links:a.uoms.links,meta:a.uoms.meta},null,8,["links","meta"])):w("",!0)])])])]),u.value?(i(),S(T,{key:0,uom:_.value,type:h.value,showModal:u.value,onModalClose:j},null,8,["uom","type","showModal"])):w("",!0)]}),_:1})],64))}};export{Qe as default};

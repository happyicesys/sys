import{_ as U}from"./Authenticated.fc486f5c.js";import{_ as m}from"./Button.186f76df.js";import j from"./Form.a8e94d37.js";import{_ as D}from"./Paginator.f8fa0454.js";import{_ as I,r as A}from"./SearchInput.96c0b981.js";import{_ as E}from"./MultiSelect.718cb95e.js";import{_ as P,a as b}from"./TableData.e074782f.js";import{_ as T}from"./TableHeadSort.b6c4de55.js";import{g as c,h as R,f as x,a as t,u as i,w as o,F as B,o as d,Z,b as e,d as f,t as g,k as q,l as w,c as S,O as k}from"./app.4ea561de.js";import{r as z}from"./PlusIcon.a6e4a920.js";import{r as G}from"./BackspaceIcon.f410e87c.js";import{r as H}from"./PencilSquareIcon.aa230263.js";import{r as J}from"./TrashIcon.f8b79586.js";import"./open-closed.9b3740ac.js";import"./use-resolve-button-type.a595af34.js";import"./RectangleStackIcon.bf7bcecf.js";import"./FormInput.176630f6.js";import"./Modal.8da25faa.js";import"./disposables.ca90838c.js";import"./ArrowUturnLeftIcon.1cca62d7.js";import"./CheckCircleIcon.5a6bdcf3.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.8a8cc677.js";const Q=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (UOM) ",-1),W={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},X={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Y={class:"flex justify-end"},ee=e("span",null," Create ",-1),te={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},oe={class:"mt-3"},ae={class:"flex space-x-1"},ne=e("span",null," Search ",-1),le=e("span",null," Reset ",-1),re={class:"flex flex-col space-y-2"},ie={class:"text-sm text-gray-700 leading-5 flex space-x-1"},de=e("span",null,"Showing",-1),ue={class:"font-medium"},me=e("span",null,"to",-1),ce={class:"font-medium"},fe=e("span",null,"of",-1),ge={class:"font-medium"},pe=e("span",null,"results",-1),xe={class:"mt-6 flex flex-col"},_e={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},he={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ve={class:"min-w-full border-separate",style:{"border-spacing":"0"}},ye={class:"bg-gray-100"},be={class:"divide-x divide-gray-200"},we={class:"bg-white"},ke={class:"flex justify-center space-x-1"},Ce=e("span",null," Edit ",-1),$e=e("span",null," Delete ",-1),Pe={key:0},Be=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Se=[Be],We={__name:"Index",props:{uoms:Object},setup(a){const n=c({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),u=c(!1),_=c(),h=c(""),v=c([]);R(()=>{v.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],n.value.numberPerPage=v.value[0]});function V(){h.value="create",_.value=null,u.value=!0}function N(r){!confirm("Are you sure to delete "+r.name+"?")||k.delete("/uoms/"+r.id)}function M(r){h.value="update",_.value=r,u.value=!0}function y(){k.get("/uoms",{...n.value,numberPerPage:n.value.numberPerPage.id},{preserveState:!0,replace:!0})}function L(){k.get("/uoms")}function O(r){n.value.sortKey=r,n.value.sortBy=!n.value.sortBy,y()}function F(){u.value=!1}return(r,s)=>(d(),x(B,null,[t(i(Z),{title:"UOM"}),t(U,null,{header:o(()=>[Q]),default:o(()=>{var C,$;return[e("div",W,[e("div",X,[e("div",Y,[t(m,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[0]||(s[0]=l=>V())},{default:o(()=>[t(i(z),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})]),e("div",te,[t(I,{placeholderStr:"Name",modelValue:n.value.name,"onUpdate:modelValue":s[1]||(s[1]=l=>n.value.name=l)},{default:o(()=>[f(" Name ")]),_:1},8,["modelValue"])]),e("div",se,[e("div",oe,[e("div",ae,[t(m,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[2]||(s[2]=l=>y())},{default:o(()=>[t(i(A),{class:"h-4 w-4","aria-hidden":"true"}),ne]),_:1}),t(m,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[3]||(s[3]=l=>L())},{default:o(()=>[t(i(G),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1})])]),e("div",re,[e("p",ie,[de,e("span",ue,g((C=a.uoms.meta.from)!=null?C:0),1),me,e("span",ce,g(($=a.uoms.meta.to)!=null?$:0),1),fe,e("span",ge,g(a.uoms.meta.total),1),pe]),t(E,{modelValue:n.value.numberPerPage,"onUpdate:modelValue":s[4]||(s[4]=l=>n.value.numberPerPage=l),options:v.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:y},null,8,["modelValue","options"])])])]),e("div",xe,[e("div",_e,[e("div",he,[e("table",ve,[e("thead",ye,[e("tr",be,[t(P,null,{default:o(()=>[f(" # ")]),_:1}),t(T,{modelName:"name",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:s[5]||(s[5]=l=>O("name"))},{default:o(()=>[f(" Name ")]),_:1},8,["sortKey","sortBy"]),t(P)])]),e("tbody",we,[(d(!0),x(B,null,q(a.uoms.data,(l,p)=>(d(),x("tr",{key:l.id,class:"divide-x divide-gray-200"},[t(b,{currentIndex:p,totalLength:a.uoms.length,inputClass:"text-center"},{default:o(()=>[f(g(a.uoms.meta.from+p),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:p,totalLength:a.uoms.length,inputClass:"text-left"},{default:o(()=>[f(g(l.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:p,totalLength:a.uoms.length,inputClass:"text-center"},{default:o(()=>[e("div",ke,[t(m,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:K=>M(l)},{default:o(()=>[t(i(H),{class:"w-4 h-4"}),Ce]),_:2},1032,["onClick"]),t(m,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:K=>N(l)},{default:o(()=>[t(i(J),{class:"w-4 h-4"}),$e]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),a.uoms.data.length?w("",!0):(d(),x("tr",Pe,Se))])]),a.uoms.data.length?(d(),S(D,{key:0,links:a.uoms.links,meta:a.uoms.meta},null,8,["links","meta"])):w("",!0)])])])]),u.value?(d(),S(j,{key:0,uom:_.value,type:h.value,showModal:u.value,onModalClose:F},null,8,["uom","type","showModal"])):w("",!0)]}),_:1})],64))}};export{We as default};

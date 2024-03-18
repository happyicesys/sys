import{_ as T}from"./Authenticated.29c5076b.js";import{_ as x}from"./Button.4b8981fe.js";import j from"./Form.793c1f36.js";import{_ as D}from"./Paginator.e614741d.js";import{_ as O}from"./SearchInput.4a4ac3b9.js";import{_ as A}from"./MultiSelect.64d3ffc2.js";import{_ as w,a as p}from"./TableData.01fe7451.js";import{_ as E}from"./TableHeadSort.ae636578.js";import{g,h as R,f as _,a as t,u as d,w as s,F as B,o as u,Z as U,b as e,d as i,t as c,k as Z,l as k,c as S,O as C}from"./app.c8734f48.js";import{r as q}from"./PlusIcon.12e7dc3e.js";import{r as z}from"./MagnifyingGlassIcon.3ac4d8fb.js";import{r as G}from"./BackspaceIcon.f0bd4e7c.js";import{r as H}from"./PencilSquareIcon.ae1c0ad1.js";import{r as J}from"./TrashIcon.5bcff779.js";import"./keyboard.d1255e04.js";import"./use-resolve-button-type.cb4535c6.js";import"./RectangleStackIcon.08fda477.js";import"./FormInput.06b6bdfe.js";import"./Modal.ab16ba7c.js";import"./disposables.1619ac17.js";import"./ArrowUturnLeftIcon.c6295de0.js";import"./CheckCircleIcon.d7bc9c9a.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.3b89a0cf.js";const Q=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Tax) ",-1),W={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},X={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Y={class:"flex justify-end"},ee=e("span",null," Create ",-1),te={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ae={class:"mt-3"},oe={class:"flex space-x-1"},ne=e("span",null," Search ",-1),le=e("span",null," Reset ",-1),re={class:"flex flex-col space-y-2"},ie={class:"text-sm text-gray-700 leading-5 flex space-x-1"},de=e("span",null,"Showing",-1),ue={class:"font-medium"},ce=e("span",null,"to",-1),me={class:"font-medium"},fe=e("span",null,"of",-1),xe={class:"font-medium"},ge=e("span",null,"results",-1),pe={class:"mt-6 flex flex-col"},_e={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},he={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ve={class:"min-w-full border-separate",style:{"border-spacing":"0"}},ye={class:"bg-gray-100"},be={class:"divide-x divide-gray-200"},we={class:"bg-white"},ke={class:"flex justify-center space-x-1"},Ce=e("span",null," Edit ",-1),$e=e("span",null," Delete ",-1),Pe={key:0},Be=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Se=[Be],Xe={__name:"Index",props:{taxes:Object},setup(a){const l=g({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),m=g(!1),h=g(),v=g(""),y=g([]);R(()=>{y.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],l.value.numberPerPage=y.value[0]});function V(){v.value="create",h.value=null,m.value=!0}function L(r){!confirm("Are you sure to delete "+r.name+"?")||C.delete("/taxes/"+r.id)}function N(r){v.value="update",h.value=r,m.value=!0}function b(){C.get("/taxes",{...l.value,numberPerPage:l.value.numberPerPage.id},{preserveState:!0,replace:!0})}function M(){C.get("/taxes")}function F(r){l.value.sortKey=r,l.value.sortBy=!l.value.sortBy,b()}function I(){m.value=!1}return(r,o)=>(u(),_(B,null,[t(d(U),{title:"Tax"}),t(T,null,{header:s(()=>[Q]),default:s(()=>{var $,P;return[e("div",W,[e("div",X,[e("div",Y,[t(x,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[0]||(o[0]=n=>V())},{default:s(()=>[t(d(q),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})]),e("div",te,[t(O,{placeholderStr:"Name",modelValue:l.value.name,"onUpdate:modelValue":o[1]||(o[1]=n=>l.value.name=n)},{default:s(()=>[i(" Name ")]),_:1},8,["modelValue"])]),e("div",se,[e("div",ae,[e("div",oe,[t(x,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[2]||(o[2]=n=>b())},{default:s(()=>[t(d(z),{class:"h-4 w-4","aria-hidden":"true"}),ne]),_:1}),t(x,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[3]||(o[3]=n=>M())},{default:s(()=>[t(d(G),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1})])]),e("div",re,[e("p",ie,[de,e("span",ue,c(($=a.taxes.meta.from)!=null?$:0),1),ce,e("span",me,c((P=a.taxes.meta.to)!=null?P:0),1),fe,e("span",xe,c(a.taxes.meta.total),1),ge]),t(A,{modelValue:l.value.numberPerPage,"onUpdate:modelValue":o[4]||(o[4]=n=>l.value.numberPerPage=n),options:y.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:b},null,8,["modelValue","options"])])])]),e("div",pe,[e("div",_e,[e("div",he,[e("table",ve,[e("thead",ye,[e("tr",be,[t(w,null,{default:s(()=>[i(" # ")]),_:1}),t(E,{modelName:"name",sortKey:l.value.sortKey,sortBy:l.value.sortBy,onSortTable:o[5]||(o[5]=n=>F("name"))},{default:s(()=>[i(" Name ")]),_:1},8,["sortKey","sortBy"]),t(w,null,{default:s(()=>[i(" Rate(%) ")]),_:1}),t(w)])]),e("tbody",we,[(u(!0),_(B,null,Z(a.taxes.data,(n,f)=>(u(),_("tr",{key:n.id,class:"divide-x divide-gray-200"},[t(p,{currentIndex:f,totalLength:a.taxes.length,inputClass:"text-center"},{default:s(()=>[i(c(a.taxes.meta.from+f),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:f,totalLength:a.taxes.length,inputClass:"text-left"},{default:s(()=>[i(c(n.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:f,totalLength:a.taxes.length,inputClass:"text-right"},{default:s(()=>[i(c(n.rate),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:f,totalLength:a.taxes.length,inputClass:"text-center"},{default:s(()=>[e("div",ke,[t(x,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:K=>N(n)},{default:s(()=>[t(d(H),{class:"w-4 h-4"}),Ce]),_:2},1032,["onClick"]),t(x,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:K=>L(n)},{default:s(()=>[t(d(J),{class:"w-4 h-4"}),$e]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),a.taxes.data.length?k("",!0):(u(),_("tr",Pe,Se))])]),a.taxes.data.length?(u(),S(D,{key:0,links:a.taxes.links,meta:a.taxes.meta},null,8,["links","meta"])):k("",!0)])])])]),m.value?(u(),S(j,{key:0,tax:h.value,type:v.value,showModal:m.value,onModalClose:I},null,8,["tax","type","showModal"])):k("",!0)]}),_:1})],64))}};export{Xe as default};

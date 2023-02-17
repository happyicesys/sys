import{_ as F}from"./Authenticated.e40c4520.js";import{_ as x}from"./Button.ff33d227.js";import K from"./Form.f0f75927.js";import{_ as D,r as A,T as w,a as E,b as p}from"./TableData.68d806df.js";import{_ as H}from"./MultiSelect.9fc6a815.js";import{_ as R}from"./TableHeadSort.bd9aea7d.js";import{i as g,j as U,o as d,g as _,a as t,b as c,w as s,F as B,H as O,d as e,t as u,m as J,p as k,c as S,f as i,J as C}from"./app.a63aabc8.js";import{r as q}from"./PlusIcon.2ad72cea.js";import{r as z}from"./BackspaceIcon.638ed693.js";import{r as G}from"./PencilSquareIcon.79a39d5b.js";import{r as Q}from"./TrashIcon.297fe744.js";import"./open-closed.c20b8099.js";import"./use-resolve-button-type.45108374.js";import"./RectangleStackIcon.a247af38.js";import"./FormInput.442482d3.js";import"./Modal.b859bd9a.js";import"./ArrowUturnLeftIcon.e47cbff1.js";import"./CheckCircleIcon.88bbb0a6.js";import"./_plugin-vue_export-helper.cdc0426e.js";const W=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Tax) ",-1),X={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},Y={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Z={class:"flex justify-end"},ee=e("span",null," Create ",-1),te={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se=i(" Name "),ae={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},oe={class:"mt-3"},ne={class:"flex space-x-1"},le=e("span",null," Search ",-1),re=e("span",null," Reset ",-1),ie={class:"flex flex-col space-y-2"},de={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ce=e("span",null,"Showing",-1),ue={class:"font-medium"},me=e("span",null,"to",-1),fe={class:"font-medium"},xe=e("span",null,"of",-1),ge={class:"font-medium"},pe=e("span",null,"results",-1),_e={class:"mt-6 flex flex-col"},he={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ve={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ye={class:"min-w-full border-separate",style:{"border-spacing":"0"}},be={class:"bg-gray-100"},we={class:"divide-x divide-gray-200"},ke=i(" # "),Ce=i(" Name "),$e=i(" Rate(%) "),Pe={class:"bg-white"},Be={class:"flex justify-center space-x-1"},Se=e("span",null," Edit ",-1),Ve=e("span",null," Delete ",-1),Le={key:0},Ne=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ie=[Ne],Ye={__name:"Index",props:{taxes:Object},setup(a){const l=g({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),m=g(!1),h=g(),v=g(""),y=g([]);U(()=>{y.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],l.value.numberPerPage=y.value[0]});function V(){v.value="create",h.value=null,m.value=!0}function L(r){!confirm("Are you sure to delete "+r.name+"?")||C.Inertia.delete("/taxes/"+r.id)}function N(r){v.value="update",h.value=r,m.value=!0}function b(){C.Inertia.get("/taxes",{...l.value,numberPerPage:l.value.numberPerPage.id},{preserveState:!0,replace:!0})}function I(){C.Inertia.get("/taxes")}function T(r){l.value.sortKey=r,l.value.sortBy=!l.value.sortBy,b()}function M(){m.value=!1}return(r,o)=>(d(),_(B,null,[t(c(O),{title:"Tax"}),t(F,null,{header:s(()=>[W]),default:s(()=>{var $,P;return[e("div",X,[e("div",Y,[e("div",Z,[t(x,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[0]||(o[0]=n=>V())},{default:s(()=>[t(c(q),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})]),e("div",te,[t(D,{placeholderStr:"Name",modelValue:l.value.name,"onUpdate:modelValue":o[1]||(o[1]=n=>l.value.name=n)},{default:s(()=>[se]),_:1},8,["modelValue"])]),e("div",ae,[e("div",oe,[e("div",ne,[t(x,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[2]||(o[2]=n=>b())},{default:s(()=>[t(c(A),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1}),t(x,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[3]||(o[3]=n=>I())},{default:s(()=>[t(c(z),{class:"h-4 w-4","aria-hidden":"true"}),re]),_:1})])]),e("div",ie,[e("p",de,[ce,e("span",ue,u(($=a.taxes.meta.from)!=null?$:0),1),me,e("span",fe,u((P=a.taxes.meta.to)!=null?P:0),1),xe,e("span",ge,u(a.taxes.meta.total),1),pe]),t(H,{modelValue:l.value.numberPerPage,"onUpdate:modelValue":o[4]||(o[4]=n=>l.value.numberPerPage=n),options:y.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:b},null,8,["modelValue","options"])])])]),e("div",_e,[e("div",he,[e("div",ve,[e("table",ye,[e("thead",be,[e("tr",we,[t(w,null,{default:s(()=>[ke]),_:1}),t(R,{modelName:"name",sortKey:l.value.sortKey,sortBy:l.value.sortBy,onSortTable:o[5]||(o[5]=n=>T("name"))},{default:s(()=>[Ce]),_:1},8,["sortKey","sortBy"]),t(w,null,{default:s(()=>[$e]),_:1}),t(w)])]),e("tbody",Pe,[(d(!0),_(B,null,J(a.taxes.data,(n,f)=>(d(),_("tr",{key:n.id,class:"divide-x divide-gray-200"},[t(p,{currentIndex:f,totalLength:a.taxes.length,inputClass:"text-center"},{default:s(()=>[i(u(a.taxes.meta.from+f),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:f,totalLength:a.taxes.length,inputClass:"text-left"},{default:s(()=>[i(u(n.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:f,totalLength:a.taxes.length,inputClass:"text-right"},{default:s(()=>[i(u(n.rate),1)]),_:2},1032,["currentIndex","totalLength"]),t(p,{currentIndex:f,totalLength:a.taxes.length,inputClass:"text-center"},{default:s(()=>[e("div",Be,[t(x,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:j=>N(n)},{default:s(()=>[t(c(G),{class:"w-4 h-4"}),Se]),_:2},1032,["onClick"]),t(x,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:j=>L(n)},{default:s(()=>[t(c(Q),{class:"w-4 h-4"}),Ve]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),a.taxes.data.length?k("",!0):(d(),_("tr",Le,Ie))])]),a.taxes.data.length?(d(),S(E,{key:0,links:a.taxes.links,meta:a.taxes.meta},null,8,["links","meta"])):k("",!0)])])])]),m.value?(d(),S(K,{key:0,tax:h.value,type:v.value,showModal:m.value,onModalClose:M},null,8,["tax","type","showModal"])):k("",!0)]}),_:1})],64))}};export{Ye as default};
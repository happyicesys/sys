import{_ as A}from"./Authenticated.c7e4681f.js";import{_ as p}from"./Button.05b52e08.js";import E from"./Form.87929598.js";import{_ as B,r as R,a as w,b as Z,c as x}from"./TableData.d68d13dd.js";import{_ as N}from"./MultiSelect.fdaf5979.js";import{_ as L}from"./TableHeadSort.506857dd.js";import{h as u,j as q,f as h,a as t,u as m,w as a,F as K,o as f,Z as z,b as e,d,t as i,l as G,m as k,c as j,O as C}from"./app.5696c208.js";import{r as H}from"./PlusIcon.08959a0b.js";import{r as J}from"./BackspaceIcon.d3d4b0ef.js";import{r as Q}from"./PencilSquareIcon.e3999755.js";import{r as W}from"./TrashIcon.07f4c23a.js";import"./open-closed.33461f34.js";import"./use-resolve-button-type.c7eed2c5.js";import"./RectangleStackIcon.ae4e0186.js";import"./FormInput.7040576b.js";import"./Modal.85fa36f3.js";import"./ArrowUturnLeftIcon.5b6993a6.js";import"./CheckCircleIcon.221d7c86.js";const X=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Simcard) ",-1),Y={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},ee={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},te={class:"flex justify-end"},se=e("span",null," Create ",-1),oe={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},le=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Telco ",-1),ae={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ne={class:"mt-3"},re={class:"flex space-x-1"},de=e("span",null," Search ",-1),ie=e("span",null," Reset ",-1),ce={class:"flex flex-col space-y-2"},ue={class:"text-sm text-gray-700 leading-5 flex space-x-1"},me=e("span",null,"Showing",-1),fe={class:"font-medium"},ge=e("span",null,"to",-1),pe={class:"font-medium"},xe=e("span",null,"of",-1),he={class:"font-medium"},_e=e("span",null,"results",-1),ve={class:"mt-6 flex flex-col"},ye={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},be={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},we={class:"min-w-full border-separate",style:{"border-spacing":"0"}},ke={class:"bg-gray-100"},Ce={class:"divide-x divide-gray-200"},$e={class:"bg-white"},Pe={class:"flex justify-center space-x-1"},Se=e("span",null," Edit ",-1),Ve=e("span",null," Delete ",-1),Be={key:0},Ne=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Le=[Ne],Qe={__name:"Index",props:{simcards:Object,telcos:Object},setup(n){const I=n,s=u({code:"",phone_number:"",telco_id:"",sortKey:"",sortBy:!0,numberPerPage:100}),g=u(!1),_=u(),v=u(""),y=u([]),$=u([]);q(()=>{y.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],s.value.numberPerPage=y.value[0],$.value=I.telcos.data.map(r=>({id:r.id,name:r.name}))});function M(){v.value="create",_.value=null,g.value=!0}function O(r){!confirm("Are you sure to delete "+r.name+"?")||C.delete("/simcards/"+r.id)}function T(r){v.value="update",_.value=r,g.value=!0}function b(){C.get("/simcards",{...s.value,telco_id:s.value.telco_id.id,numberPerPage:s.value.numberPerPage.id},{preserveState:!0,replace:!0})}function F(){C.get("/simcards")}function P(r){s.value.sortKey=r,s.value.sortBy=!s.value.sortBy,b()}function U(){g.value=!1}return(r,o)=>(f(),h(K,null,[t(m(z),{title:"Simcard"}),t(A,null,{header:a(()=>[X]),default:a(()=>{var S,V;return[e("div",Y,[e("div",ee,[e("div",te,[t(p,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[0]||(o[0]=l=>M())},{default:a(()=>[t(m(H),{class:"h-4 w-4","aria-hidden":"true"}),se]),_:1})]),e("div",oe,[t(B,{placeholderStr:"Simcard Number",modelValue:s.value.code,"onUpdate:modelValue":o[1]||(o[1]=l=>s.value.code=l)},{default:a(()=>[d(" Simcard Number ")]),_:1},8,["modelValue"]),t(B,{placeholderStr:"Phone Number",modelValue:s.value.phone_number,"onUpdate:modelValue":o[2]||(o[2]=l=>s.value.phone_number=l)},{default:a(()=>[d(" Phone Number ")]),_:1},8,["modelValue"]),e("div",null,[le,t(N,{modelValue:s.value.telco_id,"onUpdate:modelValue":o[3]||(o[3]=l=>s.value.telco_id=l),options:$.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),e("div",ae,[e("div",ne,[e("div",re,[t(p,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[4]||(o[4]=l=>b())},{default:a(()=>[t(m(R),{class:"h-4 w-4","aria-hidden":"true"}),de]),_:1}),t(p,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[5]||(o[5]=l=>F())},{default:a(()=>[t(m(J),{class:"h-4 w-4","aria-hidden":"true"}),ie]),_:1})])]),e("div",ce,[e("p",ue,[me,e("span",fe,i((S=n.simcards.meta.from)!=null?S:0),1),ge,e("span",pe,i((V=n.simcards.meta.to)!=null?V:0),1),xe,e("span",he,i(n.simcards.meta.total),1),_e]),t(N,{modelValue:s.value.numberPerPage,"onUpdate:modelValue":o[6]||(o[6]=l=>s.value.numberPerPage=l),options:y.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:b},null,8,["modelValue","options"])])])]),e("div",ve,[e("div",ye,[e("div",be,[e("table",we,[e("thead",ke,[e("tr",Ce,[t(w,null,{default:a(()=>[d(" # ")]),_:1}),t(L,{modelName:"code",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:o[7]||(o[7]=l=>P("code"))},{default:a(()=>[d(" Simcard Number ")]),_:1},8,["sortKey","sortBy"]),t(L,{modelName:"telco_id",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:o[8]||(o[8]=l=>P("telco_id"))},{default:a(()=>[d(" Telco ")]),_:1},8,["sortKey","sortBy"]),t(w,null,{default:a(()=>[d(" Phone Number ")]),_:1}),t(w)])]),e("tbody",$e,[(f(!0),h(K,null,G(n.simcards.data,(l,c)=>(f(),h("tr",{key:l.id,class:"divide-x divide-gray-200"},[t(x,{currentIndex:c,totalLength:n.simcards.length,inputClass:"text-center"},{default:a(()=>[d(i(n.simcards.meta.from+c),1)]),_:2},1032,["currentIndex","totalLength"]),t(x,{currentIndex:c,totalLength:n.simcards.length,inputClass:"text-left"},{default:a(()=>[d(i(l.code),1)]),_:2},1032,["currentIndex","totalLength"]),t(x,{currentIndex:c,totalLength:n.simcards.length,inputClass:"text-center"},{default:a(()=>[d(i(l.telco.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(x,{currentIndex:c,totalLength:n.simcards.length,inputClass:"text-left"},{default:a(()=>[d(i(l.phone_number),1)]),_:2},1032,["currentIndex","totalLength"]),t(x,{currentIndex:c,totalLength:n.simcards.length,inputClass:"text-center"},{default:a(()=>[e("div",Pe,[t(p,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:D=>T(l)},{default:a(()=>[t(m(Q),{class:"w-4 h-4"}),Se]),_:2},1032,["onClick"]),t(p,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:D=>O(l)},{default:a(()=>[t(m(W),{class:"w-4 h-4"}),Ve]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),n.simcards.data.length?k("",!0):(f(),h("tr",Be,Le))])]),n.simcards.data.length?(f(),j(Z,{key:0,links:n.simcards.links,meta:n.simcards.meta},null,8,["links","meta"])):k("",!0)])])])]),g.value?(f(),j(E,{key:0,simcard:_.value,telcos:n.telcos,type:v.value,showModal:g.value,onModalClose:U},null,8,["simcard","telcos","type","showModal"])):k("",!0)]}),_:1})],64))}};export{Qe as default};

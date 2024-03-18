import{_ as j}from"./Authenticated.3746eee6.js";import{_ as p}from"./Button.58bd898c.js";import O from"./Form.c512deee.js";import{_ as A}from"./Paginator.bcc765b7.js";import{_ as D}from"./SearchInput.06b22109.js";import{_ as E}from"./MultiSelect.c45640a2.js";import{_ as B,a as x}from"./TableData.5c05e068.js";import{_ as T}from"./TableHeadSort.01aebfb2.js";import{g,h as U,f as h,a as t,u as d,w as o,F as L,o as c,Z as R,b as e,d as i,t as u,k as W,l as w,c as N,O as k}from"./app.b43730ab.js";import{r as Z}from"./PlusIcon.4f6f6b12.js";import{r as q}from"./MagnifyingGlassIcon.b24cbc20.js";import{r as z}from"./BackspaceIcon.bd842831.js";import{r as G}from"./PencilSquareIcon.3a23cef5.js";import{r as H}from"./TrashIcon.38d855de.js";import"./keyboard.47431282.js";import"./use-resolve-button-type.325bf74b.js";import"./RectangleStackIcon.1eebd4d2.js";import"./FormInput.f3c4cd1e.js";import"./Modal.9c75dfdd.js";import"./disposables.3d165ee4.js";import"./ArrowUturnLeftIcon.ce451c08.js";import"./CheckCircleIcon.c521a4b4.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.2b5d5e81.js";const J=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Weightage (Location Type) ",-1),Q={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},X={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Y={class:"flex justify-end"},ee=e("span",null," Create ",-1),te={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},oe={class:"mt-3"},ae={class:"flex space-x-1"},ne=e("span",null," Search ",-1),le=e("span",null," Reset ",-1),re={class:"flex flex-col space-y-2"},ie={class:"text-sm text-gray-700 leading-5 flex space-x-1"},de=e("span",null,"Showing",-1),ce={class:"font-medium"},ue=e("span",null,"to",-1),me={class:"font-medium"},fe=e("span",null,"of",-1),pe={class:"font-medium"},ge=e("span",null,"results",-1),xe={class:"mt-6 flex flex-col"},he={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},_e={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ye={class:"min-w-full border-separate",style:{"border-spacing":"0"}},ve={class:"bg-gray-100"},be={class:"divide-x divide-gray-200"},we={class:"bg-white"},ke={class:"flex justify-center space-x-1"},Ce=e("span",null," Edit ",-1),$e=e("span",null," Delete ",-1),Pe={key:0},Be=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Te=[Be],Xe={__name:"Index",props:{locationTypes:Object},setup(l){const a=g({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),m=g(!1),_=g(),y=g(""),v=g([]);U(()=>{v.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],a.value.numberPerPage=v.value[0]});function S(){y.value="create",_.value=null,m.value=!0}function V(r){!confirm("Are you sure to delete "+r.name+"?")||k.delete("/location-types/"+r.id)}function K(r){y.value="update",_.value=r,m.value=!0}function b(){k.get("/location-types",{...a.value,numberPerPage:a.value.numberPerPage.id},{preserveState:!0,replace:!0})}function M(){k.get("/location-types")}function C(r){a.value.sortKey=r,a.value.sortBy=!a.value.sortBy,b()}function F(){m.value=!1}return(r,s)=>(c(),h(L,null,[t(d(R),{title:"Location Type"}),t(j,null,{header:o(()=>[J]),default:o(()=>{var $,P;return[e("div",Q,[e("div",X,[e("div",Y,[t(p,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[0]||(s[0]=n=>S())},{default:o(()=>[t(d(Z),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})]),e("div",te,[t(D,{placeholderStr:"Name",modelValue:a.value.name,"onUpdate:modelValue":s[1]||(s[1]=n=>a.value.name=n)},{default:o(()=>[i(" Name ")]),_:1},8,["modelValue"])]),e("div",se,[e("div",oe,[e("div",ae,[t(p,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[2]||(s[2]=n=>b())},{default:o(()=>[t(d(q),{class:"h-4 w-4","aria-hidden":"true"}),ne]),_:1}),t(p,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[3]||(s[3]=n=>M())},{default:o(()=>[t(d(z),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1})])]),e("div",re,[e("p",ie,[de,e("span",ce,u(($=l.locationTypes.meta.from)!=null?$:0),1),ue,e("span",me,u((P=l.locationTypes.meta.to)!=null?P:0),1),fe,e("span",pe,u(l.locationTypes.meta.total),1),ge]),t(E,{modelValue:a.value.numberPerPage,"onUpdate:modelValue":s[4]||(s[4]=n=>a.value.numberPerPage=n),options:v.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:b},null,8,["modelValue","options"])])])]),e("div",xe,[e("div",he,[e("div",_e,[e("table",ye,[e("thead",ve,[e("tr",be,[t(B,null,{default:o(()=>[i(" # ")]),_:1}),t(T,{modelName:"name",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:s[5]||(s[5]=n=>C("name"))},{default:o(()=>[i(" Name ")]),_:1},8,["sortKey","sortBy"]),t(T,{modelName:"weightage",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:s[6]||(s[6]=n=>C("weightage"))},{default:o(()=>[i(" Weightage ")]),_:1},8,["sortKey","sortBy"]),t(B)])]),e("tbody",we,[(c(!0),h(L,null,W(l.locationTypes.data,(n,f)=>(c(),h("tr",{key:n.id,class:"divide-x divide-gray-200"},[t(x,{currentIndex:f,totalLength:l.locationTypes.length,inputClass:"text-center"},{default:o(()=>[i(u(l.locationTypes.meta.from+f),1)]),_:2},1032,["currentIndex","totalLength"]),t(x,{currentIndex:f,totalLength:l.locationTypes.length,inputClass:"text-left"},{default:o(()=>[i(u(n.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(x,{currentIndex:f,totalLength:l.locationTypes.length,inputClass:"text-center"},{default:o(()=>[i(u(n.weightage),1)]),_:2},1032,["currentIndex","totalLength"]),t(x,{currentIndex:f,totalLength:l.locationTypes.length,inputClass:"text-center"},{default:o(()=>[e("div",ke,[t(p,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:I=>K(n)},{default:o(()=>[t(d(G),{class:"w-4 h-4"}),Ce]),_:2},1032,["onClick"]),t(p,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:I=>V(n)},{default:o(()=>[t(d(H),{class:"w-4 h-4"}),$e]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),l.locationTypes.data.length?w("",!0):(c(),h("tr",Pe,Te))])]),l.locationTypes.data.length?(c(),N(A,{key:0,links:l.locationTypes.links,meta:l.locationTypes.meta},null,8,["links","meta"])):w("",!0)])])])]),m.value?(c(),N(O,{key:0,locationType:_.value,type:y.value,showModal:m.value,onModalClose:F},null,8,["locationType","type","showModal"])):w("",!0)]}),_:1})],64))}};export{Xe as default};

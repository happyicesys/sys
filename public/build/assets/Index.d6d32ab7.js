import{_ as A}from"./Authenticated.c6c7dbae.js";import{_ as p}from"./Button.52c6fc2d.js";import F from"./Form.6976fdca.js";import{_ as x,a as K,b as m}from"./TableData.cfc15ede.js";import{_ as V,r as D}from"./SearchInput.bbdee196.js";import{_ as T}from"./MultiSelect.5fa2f578.js";import{_ as R}from"./TableHeadSort.2e5b3d14.js";import{h,j as Z,f as _,a as t,u as f,w as s,F as N,o as c,Z as q,b as e,c as C,m as v,d as r,t as i,l as z,O as P}from"./app.1f3b9bf1.js";import{r as G}from"./PlusIcon.25aa1944.js";import{r as H}from"./BackspaceIcon.7b2b9ec7.js";import{r as J}from"./PencilSquareIcon.a0882f19.js";import{r as Q}from"./TrashIcon.ea1917df.js";import"./open-closed.56f1f1ae.js";import"./use-resolve-button-type.9be7fef7.js";import"./RectangleStackIcon.92468871.js";import"./FormInput.2df82814.js";import"./Modal.c70f61b2.js";import"./ArrowUturnLeftIcon.812e4f4c.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.c07ee110.js";const W=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Profiles ",-1),X={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},Y={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},ee={class:"flex justify-end"},te=e("span",null," Create ",-1),se={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},le={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ne={class:"mt-3"},oe={class:"flex space-x-1"},ae=e("span",null," Search ",-1),re=e("span",null," Reset ",-1),ie={class:"flex flex-col space-y-2"},de={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ue=e("span",null,"Showing",-1),ce={class:"font-medium"},me=e("span",null,"to",-1),fe={class:"font-medium"},ge=e("span",null,"of",-1),pe={class:"font-medium"},xe=e("span",null,"results",-1),he={class:"mt-6 flex flex-col"},_e={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ve={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ye={class:"min-w-full border-separate",style:{"border-spacing":"0"}},be={class:"bg-gray-100"},we={class:"divide-x divide-gray-200"},ke={class:"bg-white"},Ce={class:"flex justify-center space-x-1"},Pe=e("span",null," Edit ",-1),$e=e("span",null," Delete ",-1),Le={key:0},Ve=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ne=[Ve],Je={__name:"Index",props:{profiles:Object,countries:Object,can:Object},setup(l){const B=l,a=h({name:"",uen:"",sortKey:"",sortBy:!0,numberPerPage:100}),g=h(!1),y=h(),b=h(""),w=h([]);Z(()=>{w.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],a.value.numberPerPage=w.value[0]});function S(){b.value="create",y.value=null,g.value=!0}function j(d){!confirm("Are you sure to delete "+d.name+"?")||P.delete("/profiles/"+d.id)}function E(d){b.value="update",y.value=d,g.value=!0}function k(){P.get("/profiles",{...a.value,numberPerPage:a.value.numberPerPage.id},{preserveState:!0,replace:!0})}function I(){P.get("/profiles")}function U(d){a.value.sortKey=d,a.value.sortBy=!a.value.sortBy,k()}function M(){g.value=!1}return(d,o)=>(c(),_(N,null,[t(f(q),{title:"Profiles"}),t(A,null,{header:s(()=>[W]),default:s(()=>{var $,L;return[e("div",X,[e("div",Y,[e("div",ee,[l.can.create?(c(),C(p,{key:0,class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[0]||(o[0]=n=>S())},{default:s(()=>[t(f(G),{class:"h-4 w-4","aria-hidden":"true"}),te]),_:1})):v("",!0)]),e("div",se,[t(V,{placeholderStr:"Name",modelValue:a.value.name,"onUpdate:modelValue":o[1]||(o[1]=n=>a.value.name=n)},{default:s(()=>[r(" Name ")]),_:1},8,["modelValue"]),t(V,{placeholderStr:"UEN",modelValue:a.value.uen,"onUpdate:modelValue":o[2]||(o[2]=n=>a.value.uen=n)},{default:s(()=>[r(" UEN ")]),_:1},8,["modelValue"])]),e("div",le,[e("div",ne,[e("div",oe,[t(p,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[3]||(o[3]=n=>k())},{default:s(()=>[t(f(D),{class:"h-4 w-4","aria-hidden":"true"}),ae]),_:1}),t(p,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[4]||(o[4]=n=>I())},{default:s(()=>[t(f(H),{class:"h-4 w-4","aria-hidden":"true"}),re]),_:1})])]),e("div",ie,[e("p",de,[ue,e("span",ce,i(($=l.profiles.meta.from)!=null?$:0),1),me,e("span",fe,i((L=l.profiles.meta.to)!=null?L:0),1),ge,e("span",pe,i(l.profiles.meta.total),1),xe]),t(T,{modelValue:a.value.numberPerPage,"onUpdate:modelValue":o[5]||(o[5]=n=>a.value.numberPerPage=n),options:w.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:k},null,8,["modelValue","options"])])])]),e("div",he,[e("div",_e,[e("div",ve,[e("table",ye,[e("thead",be,[e("tr",we,[t(x,null,{default:s(()=>[r(" # ")]),_:1}),t(R,{modelName:"name",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:o[6]||(o[6]=n=>U("name"))},{default:s(()=>[r(" Name ")]),_:1},8,["sortKey","sortBy"]),t(x,null,{default:s(()=>[r(" Alias ")]),_:1}),t(x,null,{default:s(()=>[r(" UEN ")]),_:1}),t(x,null,{default:s(()=>[r(" Address ")]),_:1}),t(x)])]),e("tbody",ke,[(c(!0),_(N,null,z(l.profiles.data,(n,u)=>(c(),_("tr",{key:n.id,class:"divide-x divide-gray-200"},[t(m,{currentIndex:u,totalLength:l.profiles.length,inputClass:"text-center"},{default:s(()=>[r(i(l.profiles.meta.from+u),1)]),_:2},1032,["currentIndex","totalLength"]),t(m,{currentIndex:u,totalLength:l.profiles.length,inputClass:"text-left"},{default:s(()=>[r(i(n.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(m,{currentIndex:u,totalLength:l.profiles.length,inputClass:"text-center"},{default:s(()=>[r(i(n.alias),1)]),_:2},1032,["currentIndex","totalLength"]),t(m,{currentIndex:u,totalLength:l.profiles.length,inputClass:"text-center"},{default:s(()=>[r(i(n.uen),1)]),_:2},1032,["currentIndex","totalLength"]),t(m,{currentIndex:u,totalLength:l.profiles.length,inputClass:"text-left md:max-w-xs"},{default:s(()=>[r(i(n.address.full_address),1)]),_:2},1032,["currentIndex","totalLength"]),t(m,{currentIndex:u,totalLength:l.profiles.length,inputClass:"text-center"},{default:s(()=>[e("div",Ce,[t(p,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:O=>E(n)},{default:s(()=>[t(f(J),{class:"w-4 h-4"}),Pe]),_:2},1032,["onClick"]),t(p,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:O=>j(n)},{default:s(()=>[t(f(Q),{class:"w-4 h-4"}),$e]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),l.profiles.data.length?v("",!0):(c(),_("tr",Le,Ne))])]),l.profiles.data.length?(c(),C(K,{key:0,links:l.profiles.links,meta:l.profiles.meta},null,8,["links","meta"])):v("",!0)])])])]),g.value?(c(),C(F,{key:0,profile:y.value,countries:B.countries,type:b.value,showModal:g.value,onModalClose:M},null,8,["profile","countries","type","showModal"])):v("",!0)]}),_:1})],64))}};export{Je as default};
